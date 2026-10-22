<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\GarantiaProveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Resultado de la gestión con la empresa: el producto vuelve al local. */
class GarantiaResolverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gpr_result'  => ['required', Rule::in(array_keys(GarantiaProveedor::RESULTADOS))],
            'gpr_fecrec'  => ['nullable', 'date', 'before_or_equal:today'],
            'gpr_nroserr' => ['nullable', 'string', 'max:80'],
            'gpr_obs'     => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'gpr_result.required'        => 'Indique el resultado de la gestión.',
            'gpr_result.in'              => 'El resultado indicado no es válido.',
            'gpr_fecrec.before_or_equal' => 'La fecha de recepción no puede ser futura.',
            'gpr_nroserr.max'            => 'El número de serie no puede superar los 80 caracteres.',
            'gpr_obs.max'                => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}
