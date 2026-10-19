<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class RechazarPresupuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:3', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Contanos por qué rechazás el presupuesto.',
            'motivo.min'      => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max'      => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}
