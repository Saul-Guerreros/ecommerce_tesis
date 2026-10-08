<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ChatMensajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // endpoint público: invitados y clientes
    }

    public function rules(): array
    {
        return [
            'mensaje' => ['required', 'string', 'max:500'],
            'token' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'Escribí un mensaje.',
            'mensaje.max' => 'El mensaje puede tener como máximo 500 caracteres.',
            'token.uuid' => 'La conversación no es válida.',
        ];
    }
}