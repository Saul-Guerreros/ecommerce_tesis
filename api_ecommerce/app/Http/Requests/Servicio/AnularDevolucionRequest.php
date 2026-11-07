<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class AnularDevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Indicá por qué se anula la devolución.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max'      => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}