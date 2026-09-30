<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

        public function rules(): array
    {
        return [
            'tpv_proid'  => ['required', 'integer', 'exists:proveedor,pro_id'],
            'tpv_tcoid'  => ['required', 'integer', 'exists:tipo_comprobante,tco_id'],
            'tpv_numero' => ['required', 'digits_between:1,8'],
            'tpv_fechfn' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'tpv_tcoid.required'        => 'Debe seleccionar el tipo de comprobante.',
            'tpv_numero.required'       => 'El número de timbrado es obligatorio.',
            'tpv_numero.digits_between' => 'El timbrado debe tener hasta 8 dígitos numéricos.',
            'tpv_fechfn.required'       => 'La fecha de vencimiento es obligatoria.',
            'tpv_fechfn.date_format'    => 'La fecha de vencimiento no es válida.',
        ];
    }
}