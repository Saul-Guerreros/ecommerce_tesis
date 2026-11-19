<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [Cambiar] el plan de cuotas de una linea de la solicitud.
 *
 * No se valida acá que el plan exista para ESE producto: eso depende
 * del estado del sistema (configuracion_cuota del producto) y vive en
 * el Service, que lanza un 422 con el mismo formato.
 */
class CambiarPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuotas' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'cuotas.required' => 'Seleccioná el nuevo plan de cuotas.',
            'cuotas.integer'  => 'El plan de cuotas debe ser un número entero.',
            'cuotas.min'      => 'El plan debe tener al menos 1 cuota.',
            'cuotas.max'      => 'El plan no puede superar las 60 cuotas.',
        ];
    }
}