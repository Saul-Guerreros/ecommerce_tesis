<?php

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SincronizarCarritoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1', 'max:50'],
            'items.*.car_idpro'  => ['required', 'integer', 'exists:producto,prd_id'],
            'items.*.car_cantid' => ['required', 'numeric', 'min:1'],

            'items.*.car_modpag' => ['nullable', 'in:contado,credito'],
            'items.*.car_numcuo' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    /** required_if no funciona con comodines: "crédito exige cuotas" se valida acá. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $indice => $item) {
                $modalidad = $item['car_modpag'] ?? 'contado';

                if ($modalidad === 'credito' && empty($item['car_numcuo'])) {
                    $validator->errors()->add(
                        "items.{$indice}.car_numcuo",
                        'Debe indicar la cantidad de cuotas.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required'             => 'No hay productos para sincronizar.',
            'items.max'                  => 'Se excedió la cantidad máxima de productos a sincronizar.',
            'items.*.car_idpro.required' => 'Falta el producto en uno de los ítems.',
            'items.*.car_idpro.exists'   => 'Uno de los productos ya no está disponible.',
            'items.*.car_cantid.min'     => 'La cantidad mínima es 1.',
            'items.*.car_modpag.in'      => 'La modalidad de pago debe ser contado o crédito.',
        ];
    }
}