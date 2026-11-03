<?php

namespace App\Http\Requests\Venta;

use App\Models\Compras\MotivoNotaCredito;
use App\Services\Venta\NotaCreditoVentaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Validación de entrada de la NC de venta.
 *
 * No valida número ni timbrado: los asigna el backend desde el talonario
 * propio. Los saldos los calcula NotaCreditoVentaService (misma fuente que
 * usa el service al emitir), para que el 422 de acá y la excepción de
 * negocio de allá nunca discrepen.
 */
class NotaCreditoVentaRequest extends FormRequest
{
    private ?MotivoNotaCredito $motivo = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncv_venid' => ['required', 'integer', 'exists:venta_cab,ven_id'],
            'ncv_mncid' => ['required', 'integer', 'exists:motivo_nota_credito,mnc_id'],
            'ncv_fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'ncv_obs'   => ['nullable', 'string', 'max:255'],

            // Solo en motivos que mueven mercadería: reingresa (sí/no) y adónde.
            'ncv_reingresa' => ['nullable', 'boolean'],
            'ncv_depid'     => ['nullable', 'integer', 'exists:deposito,dpo_id'],

            'detalle'            => ['required', 'array', 'min:1'],
            'detalle.*.vnd_id'   => ['required', 'integer', 'exists:venta_detalle,vnd_id'],
            'detalle.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            // Solo en motivos que NO mueven stock: en una devolución el
            // importe lo impone el precio facturado.
            'detalle.*.precio'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function motivo(): ?MotivoNotaCredito
    {
        if ($this->motivo === null && $this->filled('ncv_mncid')) {
            $this->motivo = MotivoNotaCredito::find($this->ncv_mncid);
        }

        return $this->motivo;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $factura = DB::table('venta_cab')->where('ven_id', $this->ncv_venid)->first();

            if (!$factura) {
                return;   // la regla exists ya lo reportó
            }

            if ((int) $factura->ven_estado === 9) {
                $v->errors()->add('ncv_venid', 'La factura está anulada: no admite notas de crédito.');
                return;
            }

            $this->validarFecha($v, $factura);
            $this->validarMotivo($v);
            $this->validarObservacion($v);
            $this->validarDestino($v, $factura);
            $this->validarDetalle($v, $factura);
            $this->validarPlazoElectronico($v);

        });
    }

    /** No puede ser anterior a la emisión de la factura ni posterior a hoy. */
    private function validarFecha(Validator $v, object $factura): void
    {
        if ($this->filled('ncv_fecha') && $this->ncv_fecha < $factura->ven_fchaem) {
            $v->errors()->add(
                'ncv_fecha',
                'La fecha no puede ser anterior a la de la factura (' . $factura->ven_fchaem . ').'
            );
        }
    }

    private function validarMotivo(Validator $v): void
    {
        $motivo = $this->motivo();

        if ($motivo && $motivo->mnc_estado !== 1) {
            $v->errors()->add('ncv_mncid', 'El motivo seleccionado está desactivado.');
        }
    }

    private function validarObservacion(Validator $v): void
    {
        if ($this->motivo()?->exigeObservacion() && mb_strlen(trim((string) $this->input('ncv_obs', ''))) < 5) {
            $v->errors()->add(
                'ncv_obs',
                'Este motivo exige una observación que explique el caso (mínimo 5 caracteres).'
            );
        }
    }

    /**
     * Motivos que mueven mercadería: el usuario decide de forma explícita si
     * reingresa y adónde. Nunca se asume el depósito principal.
     */
    private function validarDestino(Validator $v, object $factura): void
    {
        if (!($this->motivo()?->afectaStock())) {
            return;
        }

        if (!$this->has('ncv_reingresa')) {
            $v->errors()->add('ncv_reingresa', 'Indique si la mercadería reingresa al stock y a qué depósito.');
            return;
        }

        if (!$this->boolean('ncv_reingresa')) {
            return;   // baja / merma: sin depósito
        }

        if (!$this->filled('ncv_depid')) {
            $v->errors()->add('ncv_depid', 'Seleccione el depósito de destino.');
            return;
        }

        $valido = DB::table('deposito')
            ->where('dpo_id', $this->ncv_depid)
            ->where('dpo_sucid', $factura->ven_sucid)
            ->where('dpo_estado', 1)
            ->exists();

        if (!$valido) {
            $v->errors()->add('ncv_depid', 'El depósito no existe, está inactivo o no es de la sucursal emisora.');
        }
    }

    private function validarDetalle(Validator $v, object $factura): void
    {
        $motivo      = $this->motivo();
        $afectaStock = $motivo?->afectaStock() ?? false;
        $service     = app(NotaCreditoVentaService::class);
        $saldos      = $service->saldosPorLinea((int) $factura->ven_id);
        $decimales   = DB::table('unidad_medida')->pluck('umd_decimal', 'umd_codigo');
        $vistos      = [];

        foreach ((array) $this->input('detalle', []) as $i => $linea) {
            if (empty($linea['vnd_id'])) {
                continue;
            }

            if (in_array($linea['vnd_id'], $vistos, true)) {
                $v->errors()->add("detalle.$i.vnd_id", 'Ese ítem está repetido en el detalle.');
                continue;
            }

            $vistos[] = $linea['vnd_id'];

            $vnd = DB::table('venta_detalle')->where('vnd_id', $linea['vnd_id'])->first();

            if (!$vnd || (int) $vnd->vnd_venid !== (int) $factura->ven_id) {
                $v->errors()->add("detalle.$i.vnd_id", 'Ese ítem no pertenece a la factura indicada.');
                continue;
            }

            $saldo    = $service->saldosDeLinea($vnd, $saldos);
            $cantidad = (float) ($linea['cantidad'] ?? 0);

            // --- Cantidad: entera si la unidad no admite decimales ---
            if (!(bool) ($decimales[$vnd->vnd_umedida] ?? 0) && abs($cantidad - round($cantidad)) > 0.0001) {
                $v->errors()->add("detalle.$i.cantidad", 'Esta unidad de medida solo admite cantidades enteras.');
            }

            // --- Cantidad: fija (error de precio) o tope = saldo ---
            if ($motivo?->cantidadFija()) {
                if (abs($cantidad - $saldo['cantidad']) > 0.0001) {
                    $v->errors()->add(
                        "detalle.$i.cantidad",
                        "Para este motivo la cantidad es la de las unidades vigentes de la línea ({$saldo['cantidad']})."
                    );
                }
            } elseif ($cantidad > $saldo['cantidad'] + 0.0001) {
                $v->errors()->add(
                    "detalle.$i.cantidad",
                    "La cantidad supera el saldo acreditable de esa línea ({$saldo['cantidad']})."
                );
            }

            // --- Importe: fijo en devoluciones, editable y acotado en ajustes ---
            if ($afectaStock) {
                // El importe lo calcula el backend (precio neto vigente de la
                // línea): acá solo se comprueba que quede algo por acreditar.
                if ($saldo['importe'] < 0.01) {
                    $v->errors()->add(
                        "detalle.$i.cantidad",
                        'Esta línea no tiene importe acreditable (ya fue acreditada por completo).'
                    );
                }
                continue;
            }

            $precio = (float) ($linea['precio'] ?? 0);

            if ($precio < 0.01) {
                $v->errors()->add("detalle.$i.precio", 'Debe indicar el importe unitario a acreditar (mayor a cero).');
                continue;
            }

            if ($precio > (float) $vnd->vnd_precio + 0.01) {
                $v->errors()->add(
                    "detalle.$i.precio",
                    'El importe unitario no puede superar el precio facturado (' . $vnd->vnd_precio . ').'
                );
                continue;
            }

            if ($cantidad * $precio > $saldo['importe'] + 1) {
                $v->errors()->add(
                    "detalle.$i.precio",
                    "El importe supera lo que queda acreditable de la línea ({$saldo['importe']})."
                );
            }
        }
    }

        /**
     * Guía 23.5: la DNIT acepta la fecha de emisión dentro de -720 h / +120 h.
     * Se valida acá para que el usuario lo vea en el formulario y no como un
     * rechazo asíncrono quince minutos después.
     */
    private function validarPlazoElectronico(Validator $v): void
    {
        if (!config('facturacion.activa') || !$this->filled('ncv_fecha')) {
            return;
        }

        $limite = now()->subDays(29)->toDateString();

        if ($this->ncv_fecha < $limite) {
            $v->errors()->add(
                'ncv_fecha',
                "Una nota de crédito electrónica no puede emitirse con más de 30 días de atraso "
                . "(desde {$limite})."
            );
        }
    }

    public function messages(): array
    {
        return [
            'ncv_venid.required'        => 'Debe indicar la factura a la que corresponde la nota de crédito.',
            'ncv_venid.exists'          => 'La factura indicada no existe.',
            'ncv_mncid.required'        => 'Debe seleccionar el motivo de la nota de crédito.',
            'ncv_mncid.exists'          => 'El motivo seleccionado no existe.',
            'ncv_fecha.required'        => 'La fecha de la nota de crédito es obligatoria.',
            'ncv_fecha.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'detalle.required'          => 'Debe seleccionar al menos un ítem a acreditar.',
            'detalle.min'               => 'Debe seleccionar al menos un ítem a acreditar.',
            'detalle.*.cantidad.min'    => 'La cantidad debe ser mayor a cero.',
        ];
    }
}