<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class DevolverPresupuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['obs' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'obs.required' => 'Indique qué debe corregir el técnico.',
            'obs.min'      => 'La observación debe tener al menos 5 caracteres.',
            'obs.max'      => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}
