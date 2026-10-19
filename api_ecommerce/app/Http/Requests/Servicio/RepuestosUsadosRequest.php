<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

/** T4 - Cantidades reales usadas: repuestos[osr_id] = cantidad. El tope (aprobado) lo valida el Service. */
class RepuestosUsadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repuestos'   => ['required', 'array'],
            'repuestos.*' => ['required', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'repuestos.required'  => 'No hay cantidades para guardar.',
            'repuestos.*.numeric' => 'La cantidad debe ser un número.',
            'repuestos.*.min'     => 'La cantidad no puede ser negativa.',
        ];
    }
}
