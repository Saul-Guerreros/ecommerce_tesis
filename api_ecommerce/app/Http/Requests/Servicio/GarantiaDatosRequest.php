<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del reclamo a la empresa responsable de la garantía (garantía de compra):
 * empresa, número de caso, contacto y observaciones. «Enviar» los extiende (GarantiaEnviarRequest).
 */
class GarantiaDatosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gpr_proid'    => ['nullable', 'integer', Rule::exists('proveedor', 'pro_id')->where('pro_estado', 1)],
            'gpr_nrocaso'  => ['nullable', 'string', 'max:50'],
            'gpr_contacto' => ['nullable', 'string', 'max:150'],
            'gpr_obs'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'gpr_proid.exists'  => 'La empresa seleccionada no existe o está inactiva.',
            'gpr_nrocaso.max'   => 'El número de caso no puede superar los 50 caracteres.',
            'gpr_contacto.max'  => 'El contacto no puede superar los 150 caracteres.',
            'gpr_obs.max'       => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}
