<?php

namespace App\Http\Requests\Carrito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CarritoRequest extends FormRequest{
    public function authorize(): bool{
        return true;
    }

    public function rules(): array{
        // En PUT/PATCH (cambiar cantidad) no hace falta reenviar el producto:
        // ya está fijado en la fila existente y el controller no lo toca.
        $esActualizacion = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'car_idpro'  => [$esActualizacion ? 'sometimes' : 'required', 'integer', 'exists:producto,prd_id'],
            'car_idvarp' => ['nullable', 'integer', 'exists:variacion_producto,var_proid'],
            'car_cantid' => ['required', 'numeric', 'min:1'],
        ];
    }

    /**
     * Si viene car_idvarp, esa variación debe pertenecer realmente al
     * producto indicado en car_idpro (evita mezclar variación de un
     * producto con otro producto distinto en la misma fila del carrito).
     */
    public function withValidator(Validator $validator): void{
        $validator->after(function (Validator $validator){
            if (!$this->filled('car_idvarp')){
                return;
            }

            $perteneceAlProducto = \App\Models\product\VariacionProducto::where('var_proid', $this->car_idvarp)
                ->where('var_prdid', $this->car_idpro)
                ->exists();

            if (!$perteneceAlProducto){
                $validator->errors()->add(
                    'car_idvarp',
                    'La variación seleccionada no pertenece al producto indicado.'
                );
            }
        });
    }

    public function messages(): array{
        return[
            'car_idpro.required'=> 'Debe indicar el producto.',
            'car_idpro.exists'=> 'El producto seleccionado no existe.',
            'car_idvarp.exists'=> 'La variación seleccionada no existe.',
            'car_cantid.required'=> 'La cantidad es obligatoria.',
            'car_cantid.min'=> 'La cantidad mínima es 1.',
            'car_precio.required'=> 'Falta el precio unitario.',
            'car_subtot.required'=> 'Falta el subtotal.',
            'car_total.required'=> 'Falta el total.',
        ];
    }
}