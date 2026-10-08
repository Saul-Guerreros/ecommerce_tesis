<?php

namespace App\Http\Requests\Compra;

use App\Models\Compras\ProductoEquivalencia;
use App\Models\Compras\TimbradoProveedor;
use App\Models\Compras\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class CompraRequest extends FormRequest
{
    public const DIAS_MAX_ANTIGUEDAD = 10;

    /**
     * El tipo de comprobante NO viene del cliente: este formulario solo
     * registra Facturas. Se fuerza aca para que no se pueda manipular.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['com_tcoid' => TipoComprobante::idFactura()]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'com_proid' => ['required', 'integer', 'exists:proveedor,pro_id'],
            'com_sucid' => ['required', 'integer', 'exists:sucursal,suc_id'],
            'com_depid' => ['required', 'integer', 'exists:deposito,dpo_id'],
            'com_tcoid' => ['required', 'integer', 'exists:tipo_comprobante,tco_id'],

            // Se recibe el ID del timbrado del proveedor y el controlador
            // copia numero y vencimiento como snapshot.
            'com_tpvid'  => ['nullable', 'integer', 'exists:timbrado_proveedor,tpv_id'],

            'com_nrodoc' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{7}$/'],

            // No futura y con antiguedad maxima de 10 dias (APP_TIMEZONE = America/Asuncion).
            'com_fchaem' => [
                'required', 'date_format:Y-m-d', 'before_or_equal:today',
                'after_or_equal:' . now()->subDays(self::DIAS_MAX_ANTIGUEDAD)->toDateString(),
            ],


            'com_condic' => ['required', 'integer', 'in:0,1'],
            'com_plzdia' => ['nullable', 'integer', 'min:0'],
            'com_cuotas' => ['required_if:com_condic,1', 'nullable', 'integer', 'min:1'],
            'com_interv' => ['nullable', 'integer', 'min:0'],
            'com_fchvto' => ['nullable', 'date'],

            'com_obs' => ['nullable', 'string', 'max:100'],

            'detalle'              => ['required', 'array', 'min:1'],
            'detalle.*.prd_id'     => ['required', 'integer', 'exists:producto,prd_id'],
            'detalle.*.umd_id'     => ['required', 'integer', 'exists:unidad_medida,umd_id'],
            'detalle.*.peq_id'     => ['nullable', 'integer', 'exists:producto_equivalencia,peq_id'],
            'detalle.*.tiv_id'     => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'detalle.*.descri'     => ['required', 'string', 'max:150'],
            'detalle.*.precun'     => ['required', 'numeric', 'min:0.0001'],
            'detalle.*.canfac'     => ['required', 'numeric', 'min:0.0001'],
            'detalle.*.canrec'     => ['required', 'numeric', 'min:0'],
            'detalle.*.despor'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'detalle.*.descto'     => ['nullable', 'numeric', 'min:0'],   // descuento UNITARIO en Gs. (fuente de verdad)
            'detalle.*.tasa'       => ['nullable', 'numeric', 'min:0'],
            'detalle.*.gravada'    => ['nullable', 'numeric', 'min:0'],
            'detalle.*.iva'        => ['nullable', 'numeric', 'min:0'],

            // Totales de cabecera: ya NO se reciben; los calcula CompraService.
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarProveedor($validator);
            $this->validarSucursalDeposito($validator);
            $timbrado = $this->validarTipoYTimbrado($validator);
            $this->validarDuplicado($validator, $timbrado);
            $this->validarDetalle($validator);
        });
    }

    // ------------------------------------------------------------------

    private function validarProveedor(Validator $v): void
    {
        if (!$this->filled('com_proid')) return;

        $estado = DB::table('proveedor')->where('pro_id', $this->com_proid)->value('pro_estado');

        if ($estado !== null && (int) $estado !== 1) {
            $v->errors()->add('com_proid', 'El proveedor seleccionado está inactivo.');
        }
    }

    /** Sucursal activa y deposito activo PERTENECIENTE a esa sucursal. */
    private function validarSucursalDeposito(Validator $v): void
    {
        if ($this->filled('com_sucid')) {
            $estado = DB::table('sucursal')->where('suc_id', $this->com_sucid)->value('suc_estado');
            if ($estado !== null && (int) $estado !== 1) {
                $v->errors()->add('com_sucid', 'La sucursal seleccionada está inactiva.');
            }
        }

        if (!$this->filled('com_depid')) return;

        $deposito = DB::table('deposito')->where('dpo_id', $this->com_depid)->first();
        if (!$deposito) return;

        if ((int) $deposito->dpo_estado !== 1) {
            $v->errors()->add('com_depid', 'El depósito seleccionado está inactivo.');
        }

        if ($this->filled('com_sucid') && (int) $deposito->dpo_sucid !== (int) $this->com_sucid) {
            $v->errors()->add('com_depid', 'El depósito no pertenece a la sucursal seleccionada.');
        }
    }

    /**
     * Proveedor -> Tipo -> Timbrado -> Fecha emision.
     * Devuelve el timbrado validado (o null) para reutilizarlo.
     */
    private function validarTipoYTimbrado(Validator $v): ?TimbradoProveedor
    {
        if (!$this->filled('com_tcoid')) return null;

        $tipo = DB::table('tipo_comprobante')->where('tco_id', $this->com_tcoid)->first();
        if (!$tipo) return null;

        if ((int) $tipo->tco_estado !== 1) {
            $v->errors()->add('com_tcoid', 'El tipo de comprobante está inactivo.');
        }

        if (!$this->filled('com_tpvid')) {
            if ((int) $tipo->tco_reqtim === 1) {
                $v->errors()->add('com_tpvid', 'Este tipo de comprobante requiere seleccionar el timbrado del proveedor.');
            }
            return null;
        }

        $timbrado = TimbradoProveedor::find($this->com_tpvid);
        if (!$timbrado) return null;

        if ($timbrado->tpv_proid !== (int) $this->com_proid) {
            $v->errors()->add('com_tpvid', 'El timbrado no pertenece al proveedor seleccionado.');
            return null;
        }

        if ($timbrado->tpv_tcoid !== (int) $this->com_tcoid) {
            $v->errors()->add('com_tpvid', 'El timbrado no corresponde al tipo de comprobante seleccionado.');
            return null;
        }

        if ($timbrado->tpv_estado !== 1) {
            $v->errors()->add('com_tpvid', 'El timbrado seleccionado está desactivado.');
        }

        if ($this->filled('com_fchaem') && !$timbrado->cubreFecha($this->com_fchaem)) {
            $v->errors()->add(
                'com_tpvid',
                'El timbrado venció el ' . $timbrado->tpv_fechfn->format('d/m/Y')
                . ', antes de la fecha de emisión. Registre el timbrado nuevo del proveedor.'
            );
        }

        return $timbrado;
    }

    /** Misma regla que el indice parcial uq_com_doc_confirmado. */
    private function validarDuplicado(Validator $v, ?TimbradoProveedor $timbrado): void
    {
        if (!$this->filled('com_proid') || !$this->filled('com_tcoid') || !$this->filled('com_nrodoc')) return;

        $yaExiste = DB::table('compra_cab')
            ->where('com_proid', $this->com_proid)
            ->where('com_tcoid', $this->com_tcoid)
            ->where('com_nrodoc', $this->com_nrodoc)
            ->where('com_estfac', 1)
            ->when(
                $timbrado,
                fn ($q) => $q->where('com_timbra', $timbrado->tpv_numero),
                fn ($q) => $q->whereNull('com_timbra')
            )
            ->exists();

        if ($yaExiste) {
            $v->errors()->add('com_nrodoc', 'Esta factura ya fue registrada para este proveedor.');
        }
    }

    private function validarDetalle(Validator $v): void
    {
        foreach ((array) $this->input('detalle', []) as $i => $linea) {
            $precio = (float) ($linea['precun'] ?? 0);
            $descto = (float) ($linea['descto'] ?? 0);
            if ($descto > $precio) {
                $v->errors()->add("detalle.$i.descto", 'El descuento unitario no puede superar el precio unitario.');
            }

            if (empty($linea['prd_id'])) continue;

            $producto = DB::table('producto')->where('prd_id', $linea['prd_id'])->first();
            if (!$producto) continue;

            if ((int) $producto->prd_estado !== 1) {
                $v->errors()->add("detalle.$i.prd_id", 'Uno de los productos seleccionados está inactivo.');
            }

            
            $umdBase  = (int) $producto->prd_umdid;
            $umdLinea = (int) ($linea['umd_id'] ?? 0);
            $peqId    = $linea['peq_id'] ?? null;

            // Unidad de stock: factor 1 implícito, no lleva presentación.
            if ($umdLinea === $umdBase) {
                if (!empty($peqId)) {
                    $v->errors()->add("detalle.$i.peq_id", 'La unidad de stock no usa equivalencia.');
                }
                continue;
            }

            // Unidad alternativa: debe indicar QUÉ presentación (Caja x10, Caja x12...).
            if (empty($peqId)) {
                $v->errors()->add("detalle.$i.peq_id", 'Debe seleccionar la presentación de la unidad elegida.');
                continue;
            }

            // Propia del producto o heredada de su modelo base (variantes).
            $existe = ProductoEquivalencia::disponiblesPara((int) $linea['prd_id'])
                ->where('peq_id', $peqId)
                ->where('peq_umdid', $umdLinea)
                ->where('peq_estado', 1)
                ->exists();

            if (!$existe) {
                $v->errors()->add("detalle.$i.peq_id", 'La presentación seleccionada no está activa o no corresponde al producto y la unidad.');
            }

        }
    }

    public function messages(): array
    {
        return [
            'com_proid.required'  => 'Debe seleccionar el proveedor.',
            'com_proid.exists'    => 'El proveedor seleccionado no existe.',
            'com_sucid.required'  => 'Debe seleccionar la sucursal.',
            'com_depid.required'  => 'Debe seleccionar el depósito.',
            'com_tcoid.required'  => 'No está configurado el tipo de comprobante Factura.',
            'com_tpvid.exists'    => 'El timbrado seleccionado no existe.',
            'com_nrodoc.required' => 'El número de factura es obligatorio.',
            'com_nrodoc.regex'    => 'El número de factura debe tener el formato 001-001-0000000.',
            'com_fchaem.required'        => 'La fecha de emisión es obligatoria.',
            'com_fchaem.date_format'     => 'La fecha de emisión no es válida.',
            'com_fchaem.before_or_equal' => 'La fecha de emisión no puede ser posterior a hoy.',
            'com_fchaem.after_or_equal'  => 'La fecha de emisión no puede tener más de ' . self::DIAS_MAX_ANTIGUEDAD . ' días de antigüedad.',
            'com_cuotas.required_if'     => 'Debe indicar la cantidad de cuotas cuando la condición es Crédito.',
            'detalle.required'    => 'Debe cargar al menos un ítem.',
            'detalle.min'         => 'Debe cargar al menos un ítem.',
            'detalle.*.prd_id.required' => 'Falta seleccionar el producto en una de las líneas.',
            'detalle.*.precun.required' => 'Falta el precio en una de las líneas.',
            'detalle.*.precun.min'      => 'El precio debe ser mayor a 0.',
            'detalle.*.canfac.required' => 'Falta la cantidad facturada en una de las líneas.',
            'detalle.*.canfac.min'      => 'La cantidad facturada debe ser mayor a 0.',
        ];
    }
}