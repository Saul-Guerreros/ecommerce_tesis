<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            // Laravel exige un campo password_nuevo_confirmation junto a este
            'password_nuevo'  => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_actual.required' => 'Debes ingresar tu contraseña actual.',
            'password_nuevo.required'  => 'Debes ingresar la nueva contraseña.',
            'password_nuevo.min'       => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password_nuevo.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ];
    }
}