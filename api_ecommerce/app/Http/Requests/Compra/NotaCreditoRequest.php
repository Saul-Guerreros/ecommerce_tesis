<?php
namespace App\Http\Requests\Compra;

use App\Models\Compras\{MotivoNotaCredito, TimbradoProveedor, TipoComprobante};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class NotaCreditoRequest extends FormRequest
{
    private ?MotivoNotaCredito $motivo = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncr_numero' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{7}$/'],
            'ncr_fecha'  => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'ncr_tpvid'  => ['required', 'integer', 'exists:timbrado_proveedor,tpv_id'],
            'ncr_mncid'  => ['required', 'integer', 'exists:motivo_nota_credito,mnc_id'],
            'ncr_obs'    => ['nullable', 'string', 'max:100'],

            'detalle'                      => ['required', 'array', 'min:1'],
            'detalle.*.cmd_id'             => ['required', 'integer', 'exists:compra_detalle,cmd_id'],
            'detalle.*.cantidad'           => ['required', 'numeric', 'min:0.0001'],
            // Solo aplica a motivos que NO afectan stock; en los de devolucion
            // el importe lo impone el precio facturado (ver validarDetalle).
            'detalle.*.descuento_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** Motivo resuelto una sola vez (lo reusa validarDetalle). */
    private function motivo(): ?MotivoNotaCredito
    {
        if ($this->motivo === null && $this->filled('ncr_mncid')) {
            $this->motivo = MotivoNotaCredito::find($this->ncr_mncid);
        }
        return $this->motivo;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $comId  = (int) $this->route('id');
            $compra = DB::table('compra_cab')->where('com_id', $comId)->first();

            if (!$compra) {
                $validator->errors()->add('detalle', 'La compra referenciada no existe.');
                return;
            }

            if ((int) $compra->com_estfac !== 1) {
                $validator->errors()->add('detalle', 'Solo se pueden registrar notas de crédito sobre una compra Confirmada.');
                return;
            }

            $this->validarNumero($validator, $comId);
            $this->validarFecha($validator, $compra);
            $this->validarTimbrado($validator, $compra);
            $this->validarMotivo($validator);
            $this->validarDetalle($validator, $compra);
        });
    }

    private function validarNumero(Validator $v, int $comId): void
    {
        if (!$this->filled('ncr_numero')) return;

        $yaExiste = DB::table('nota_credito')
            ->where('ncr_comid', $comId)
            ->where('ncr_numero', $this->ncr_numero)
            ->exists();

        if ($yaExiste) {
            $v->errors()->add('ncr_numero', 'Ya existe una Nota de Crédito con ese número para esta compra.');
        }
    }

    /** No puede ser anterior a la fecha de la factura ni posterior a hoy. */
    private function validarFecha(Validator $v, object $compra): void
    {
        if (!$this->filled('ncr_fecha')) return;

        if ($this->ncr_fecha < $compra->com_fchaem) {
            $v->errors()->add(
                'ncr_fecha',
                'La fecha de la Nota de Crédito no puede ser anterior a la fecha de la factura (' . $compra->com_fchaem . ').'
            );
        }
    }

    /** Mismo proveedor de la factura, tipo Nota de Crédito, activo y vigente para ncr_fecha. */
    private function validarTimbrado(Validator $v, object $compra): void
    {
        if (!$this->filled('ncr_tpvid')) return;

        $timbrado = TimbradoProveedor::find($this->ncr_tpvid);
        if (!$timbrado) return;

        if ($timbrado->tpv_proid !== (int) $compra->com_proid) {
            $v->errors()->add('ncr_tpvid', 'El timbrado no pertenece al proveedor de la factura.');
            return;
        }

        $tcoNotaCredito = TipoComprobante::idNotaCredito();
        if ($tcoNotaCredito && $timbrado->tpv_tcoid !== $tcoNotaCredito) {
            $v->errors()->add('ncr_tpvid', 'El timbrado no corresponde al tipo de comprobante Nota de Crédito.');
        }

        if ($timbrado->tpv_estado !== 1) {
            $v->errors()->add('ncr_tpvid', 'El timbrado seleccionado está desactivado.');
        }

        if ($this->filled('ncr_fecha') && !$timbrado->cubreFecha($this->ncr_fecha)) {
            $v->errors()->add(
                'ncr_tpvid',
                'El timbrado venció el ' . $timbrado->tpv_fechfn->format('d/m/Y') . ', antes de la fecha de la Nota de Crédito.'
            );
        }
    }

    private function validarMotivo(Validator $v): void
    {
        $motivo = $this->motivo();

        if ($motivo && $motivo->mnc_estado !== 1) {
            $v->errors()->add('ncr_mncid', 'El motivo seleccionado está desactivado.');
        }
    }

    private function validarDetalle(Validator $v, object $compra): void
    {
        $motivo      = $this->motivo();
        $afectaStock = $motivo?->afectaStock() ?? false;

        foreach ((array) $this->input('detalle', []) as $i => $linea) {
            if (empty($linea['cmd_id'])) continue;

            $detalle = DB::table('compra_detalle')->where('cmd_id', $linea['cmd_id'])->first();

            if (!$detalle || (int) $detalle->cmd_comid !== (int) $compra->com_id) {
                $v->errors()->add("detalle.$i.cmd_id", 'Ese ítem no pertenece a la compra indicada.');
                continue;
            }

            $cantidad = (float) ($linea['cantidad'] ?? 0);

            // --- Saldo acreditable (cmd_canfac ya esta en unidad de stock) ---
            $yaAcreditado = (float) DB::table('det_nota_credito')
                ->where('dnc_cmdid', $linea['cmd_id'])
                ->sum('dnc_cantidad');

            $disponible = (float) $detalle->cmd_canfac - $yaAcreditado;

            if ($cantidad > $disponible + 0.0001) {
                $v->errors()->add(
                    "detalle.$i.cantidad",
                    "La cantidad acreditada supera el saldo disponible de esa línea ({$disponible})."
                );
            }

            // Precio neto de la linea, por unidad de STOCK, con IVA incluido.
            $factor     = max((float) $detalle->cmd_factconv, 0.0001);
            $precioNeto = round(((float) $detalle->cmd_precun - (float) $detalle->cmd_descto) / $factor, 4);

            if ($afectaStock) {
                // Devolucion: el importe no se negocia, sale del precio facturado.
                // Lo unico que se valida aca es que exista stock fisico suficiente.
                $stock = DB::table('stock')
                    ->where('sto_prdid', $detalle->cmd_prdid)
                    ->where('sto_depid', $compra->com_depid)
                    ->first();

                $stockLibre = $stock ? (float) $stock->sto_cantid - (float) $stock->sto_reserv : 0.0;

                if ($cantidad > $stockLibre + 0.0001) {
                    $v->errors()->add(
                        "detalle.$i.cantidad",
                        "No hay stock suficiente para devolver \"{$detalle->cmd_descri}\" (disponible: {$stockLibre})."
                    );
                }
                continue;
            }

            // Motivos que NO afectan stock: el importe unitario es obligatorio
            // y no puede superar el precio facturado de la linea.
            $descUnit = (float) ($linea['descuento_unitario'] ?? 0);

            if ($descUnit <= 0) {
                $v->errors()->add("detalle.$i.descuento_unitario", 'Debe indicar el importe unitario a acreditar.');
                continue;
            }

            if ($descUnit > $precioNeto + 0.01) {
                $v->errors()->add(
                    "detalle.$i.descuento_unitario",
                    "El importe unitario no puede superar el precio facturado ({$precioNeto})."
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'ncr_numero.required'       => 'El número de la Nota de Crédito es obligatorio.',
            'ncr_numero.regex'          => 'El número debe tener el formato 000-000-0000000 (13 dígitos).',
            'ncr_fecha.required'        => 'La fecha de la Nota de Crédito es obligatoria.',
            'ncr_fecha.before_or_equal' => 'La fecha de la Nota de Crédito no puede ser posterior a hoy.',
            'ncr_tpvid.required'        => 'Debe seleccionar el timbrado de la Nota de Crédito.',
            'ncr_tpvid.exists'          => 'El timbrado seleccionado no existe.',
            'ncr_mncid.required'        => 'Debe seleccionar el motivo de la Nota de Crédito.',
            'ncr_mncid.exists'          => 'El motivo seleccionado no existe.',
            'detalle.required'          => 'Debe cargar al menos un ítem.',
            'detalle.min'               => 'Debe cargar al menos un ítem.',
        ];
    }
}