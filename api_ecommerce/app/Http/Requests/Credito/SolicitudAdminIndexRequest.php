<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de la bandeja de solicitudes de credito.
 *
 * authorize() devuelve true: la autorizacion vive en el middleware
 * 'permiso:' de routes/api.php.
 */
class SolicitudAdminIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Busqueda: un campo elegido + un texto (diseño con combo).
            'campo' => ['nullable', Rule::in(['numero', 'ci', 'cliente'])],
            'q'     => ['nullable', 'string', 'max:100'],

            'estado' => ['nullable', 'integer', 'exists:estado_solicitud,esc_id'],

            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],

            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'campo.in'             => 'El criterio de búsqueda seleccionado no es válido.',
            'q.max'                => 'El texto de búsqueda no puede superar los 100 caracteres.',
            'estado.exists'        => 'El estado seleccionado no existe.',
            'desde.date_format'    => 'La fecha "Desde" no tiene un formato válido.',
            'hasta.date_format'    => 'La fecha "Hasta" no tiene un formato válido.',
            'hasta.after_or_equal' => 'La fecha "Hasta" no puede ser anterior a "Desde".',
            'per_page.max'         => 'No se pueden solicitar más de 100 solicitudes por página.',
        ];
    }
}