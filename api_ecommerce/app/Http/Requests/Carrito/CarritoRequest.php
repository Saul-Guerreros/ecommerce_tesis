<?php

namespace App\Http\Requests\Carrito;

use App\Models\product\VariacionProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CarritoRequest extends FormRequest{
    public function authorize(): bool{
        return true;
    }

    public function rules(): array{
        $esActualizacion = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'car_idpro'  => [$esActualizacion ? 'sometimes' : 'required', 'integer', 'exists:producto,prd_id'],
            'car_idvarp' => ['nullable', 'integer', 'exists:variacion_producto,var_proid'],
            'car_cantid' => ['required', 'numeric', 'min:1'],

            // El monto de la cuota NO se acepta desde el cliente.
            'car_modpag' => ['nullable', 'in:contado,credito'],
            'car_numcuo' => ['nullable', 'integer', 'min:1', 'max:60', 'required_if:car_modpag,credito'],
        ];
    }

    public function withValidator(Validator $validator): void{
        $validator->after(function (Validator $validator){
            if (! $this->filled('car_idvarp')){
                return;
            }

            $perteneceAlProducto = VariacionProducto::where('var_proid', $this->car_idvarp)
                ->where('var_prdid', $this->car_idpro)
                ->exists();

            if (! $perteneceAlProducto){
                $validator->errors()->add(
                    'car_idvarp',
                    'La variación seleccionada no pertenece al producto indicado.'
                );
            }
        });
    }

    public function messages(): array{
        return[
            'car_idpro.required'     => 'Debe indicar el producto.',
            'car_idpro.exists'       => 'El producto seleccionado no existe.',
            'car_idvarp.exists'      => 'La variación seleccionada no existe.',
            'car_cantid.required'    => 'La cantidad es obligatoria.',
            'car_cantid.min'         => 'La cantidad mínima es 1.',
            'car_modpag.in'          => 'La modalidad de pago debe ser contado o crédito.',
            'car_numcuo.required_if' => 'Debe indicar la cantidad de cuotas.',
            'car_numcuo.max'         => 'La cantidad de cuotas no puede superar 60.',
        ];
    }
}