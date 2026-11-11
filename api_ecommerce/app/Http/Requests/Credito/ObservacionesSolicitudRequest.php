<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guardado de observaciones internas sin resolver la solicitud.
 * Permite dejar notas a mitad de la evaluacion sin forzar una decision.
 */
class ObservacionesSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}