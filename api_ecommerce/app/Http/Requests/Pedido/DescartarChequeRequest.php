<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del modal de confirmación de [Descartar cheque].
 *
 * El motivo es opcional pero se pide: descartar borra lo que declaró el
 * cliente, y la fila de auditoría sin motivo no explica por qué.
 */
class DescartarChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirma' => ['accepted'],
            'motivo'   => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirma.accepted' => 'Confirmá que querés borrar los datos declarados por el cliente.',
            'motivo.max'        => 'El motivo no puede superar los 300 caracteres.',
        ];
    }
}