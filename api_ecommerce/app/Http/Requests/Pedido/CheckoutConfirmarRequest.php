<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutConfirmarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización va en el middleware
    }

    public function rules(): array
    {
        return [
            'acepta_condiciones' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        $mensaje = 'Debés aceptar las condiciones de compra y los Términos y Condiciones para confirmar el pedido.';

        return [
            'acepta_condiciones.required' => $mensaje,
            'acepta_condiciones.accepted' => $mensaje,
        ];
    }
}