<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'com_proid' => ['required', 'integer', 'exists:proveedor,pro_id'],
            'com_depid' => ['required', 'integer', 'exists:deposito,dpo_id'],
            'com_sucid' => ['required', 'integer', 'exists:sucursal,suc_id'],
            'com_tcoid' => ['required', 'integer', 'exists:tipo_comprobante,tco_id'],

            'com_timbra' => ['nullable', 'string', 'size:8'],
            'com_timbvn' => ['nullable', 'date'],
            'com_nrodoc' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{7}$/'],
            'com_fchaem' => ['required', 'date'],
            'com_nrorem' => ['nullable', 'string', 'max:15'],

            'com_condic' => ['required', 'integer', 'in:0,1'],
            'com_plzdia' => ['nullable', 'integer', 'min:0'],
            'com_cuotas' => ['required_if:com_condic,1', 'nullable', 'integer', 'min:1'],
            'com_interv' => ['nullable', 'integer', 'min:0'],
            'com_fchvto' => ['nullable', 'date'],

            'com_obs' => ['nullable', 'string', 'max:100'],

            // Detalle: al menos 1 linea, cada una con sus propias reglas.
            'detalle'              => ['required', 'array', 'min:1'],
            'detalle.*.prd_id'     => ['required', 'integer', 'exists:producto,prd_id'],
            'detalle.*.tiv_id'     => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'detalle.*.descri'     => ['required', 'string', 'max:150'],
            'detalle.*.costo'      => ['required', 'numeric', 'min:0.0001'],
            'detalle.*.canfac'     => ['required', 'numeric', 'min:0.0001'],
            'detalle.*.canrec'     => ['required', 'numeric', 'min:0'],
            'detalle.*.despor'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'detalle.*.descto'     => ['nullable', 'numeric', 'min:0'],
            'detalle.*.tasa'       => ['nullable', 'numeric', 'min:0'],
            'detalle.*.gravada'    => ['nullable', 'numeric', 'min:0'],
            'detalle.*.iva'        => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validaciones que dependen de otros registros (proveedor activo,
     * timbrado vigente, productos activos): siguen siendo validacion de
     * FORMA/consistencia del payload, por eso van aca y no en el
     * controlador, igual que en los demas Request del proyecto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Proveedor activo
            if ($this->filled('com_proid')) {
                $activo = DB::table('proveedor')
                    ->where('pro_id', $this->com_proid)
                    ->value('pro_estado');

                if ($activo !== null && (int) $activo !== 1) {
                    $validator->errors()->add('com_proid', 'El proveedor seleccionado está inactivo.');
                }
            }

            // Timbrado vigente: fecha de emision no puede ser posterior al vencimiento
            if ($this->filled('com_timbvn') && $this->filled('com_fchaem')) {
                if ($this->com_fchaem > $this->com_timbvn) {
                    $validator->errors()->add('com_fchaem', 'La fecha de emisión es posterior al vencimiento del timbrado.');
                }
            }

            // Productos activos (uno por uno, para señalar cual esta mal)
            foreach ((array) $this->input('detalle', []) as $i => $linea) {
                if (empty($linea['prd_id'])) {
                    continue;
                }

                $activo = DB::table('producto')->where('prd_id', $linea['prd_id'])->value('prd_estado');

                if ($activo !== null && (int) $activo !== 1) {
                    $validator->errors()->add("detalle.$i.prd_id", 'Uno de los productos seleccionados está inactivo.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'com_proid.required' => 'Debe seleccionar el proveedor.',
            'com_proid.exists'   => 'El proveedor seleccionado no existe.',
            'com_depid.required' => 'Debe seleccionar el depósito.',
            'com_sucid.required' => 'Falta la sucursal.',
            'com_tcoid.required' => 'Debe seleccionar el tipo de comprobante.',
            'com_nrodoc.required' => 'El número de factura es obligatorio.',
            'com_nrodoc.regex'    => 'El número de factura debe tener el formato 001-001-0000000.',
            'com_fchaem.required' => 'La fecha de emisión es obligatoria.',
            'com_cuotas.required_if' => 'Debe indicar la cantidad de cuotas cuando la condición es Crédito.',
            'detalle.required'    => 'Debe cargar al menos un ítem.',
            'detalle.min'         => 'Debe cargar al menos un ítem.',
            'detalle.*.prd_id.required' => 'Falta seleccionar el producto en una de las líneas.',
            'detalle.*.costo.required'  => 'Falta el costo en una de las líneas.',
            'detalle.*.costo.min'       => 'El costo debe ser mayor a 0.',
            'detalle.*.canfac.required' => 'Falta la cantidad facturada en una de las líneas.',
            'detalle.*.canfac.min'      => 'La cantidad facturada debe ser mayor a 0.',
        ];
    }
}