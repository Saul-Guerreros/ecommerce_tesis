<?php

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;

class SincronizarCarritoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Tope defensivo: un carrito de invitado manipulado desde
            // localStorage no deberia poder disparar cientos de inserts.
            'items'               => ['required', 'array', 'min:1', 'max:50'],
            'items.*.car_idpro'   => ['required', 'integer', 'exists:producto,prd_id'],
            'items.*.car_cantid'  => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'No hay productos para sincronizar.',
            'items.max'                   => 'Se excedió la cantidad máxima de productos a sincronizar.',
            'items.*.car_idpro.required'  => 'Falta el producto en uno de los ítems.',
            'items.*.car_idpro.exists'    => 'Uno de los productos ya no está disponible.',
            'items.*.car_cantid.min'      => 'La cantidad mínima es 1.',
        ];
    }
}