<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $producto = $this->route('producto'); 

        return [
            'prd_marid'  => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_tivid'  => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'prd_umdid'  => ['required', 'integer', 'exists:unidad_medida,umd_id'],
            'prd_codinv' => [
                'required', 'string', 'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($producto?->prd_id, 'prd_id'),
            ],
            'prd_descri' => ['required', 'string'],
            'prd_precio' => ['required', 'numeric', 'min:0'],
            'prd_stock'  => ['required', 'integer', 'min:0'],
            'prd_stkmin' => ['required', 'integer', 'min:0'],
            'prd_estado' => ['required', 'integer', 'in:0,1'],
            'prd_afestk' => ['sometimes', 'boolean'],
            'prd_padre_id' => [ 'nullable', 'integer',
				Rule::exists('producto', 'prd_id')->where(fn ($q) => $q->whereNull('prd_padre_id')),
				Rule::notIn([$producto?->prd_id ?? null]),
				function ($attribute, $value, $fail) use ($producto) {
					if ($value && $producto && $producto->variantes()->exists()) {
						$fail('ESTE PRODUCTO YA ES MODELO BASE DE OTRAS VARIANTES; NO PUEDE A SU VEZ SER VARIANTE DE OTRO PRODUCTO.');
					}
				},
			 ],

        ];
    }

    public function messages(): array
    {
        return [
            'prd_marid.exists' => 'La marca seleccionada no existe.',
            'prd_tivid.exists' => 'El tipo de IVA seleccionado no existe.',
            'prd_umdid.exists' => 'La unidad de medida seleccionada no existe.',
        ];
    }
}
