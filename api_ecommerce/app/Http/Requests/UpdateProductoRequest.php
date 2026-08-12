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
        $producto = $this->route('producto'); // model binding, ver rutas

        return [
            'prd_marid'  => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_catid'  => ['required', 'integer', 'exists:categoria,cat_id'],
            'prd_titulo' => ['required', 'string', 'max:45'],
            'prd_codinv' => [
                'required', 'string', 'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($producto?->prd_id, 'prd_id'),
            ],
            'prd_descri' => ['required', 'string'],
            'prd_precio' => ['required', 'numeric', 'min:0'],
            'prd_stock'  => ['required', 'integer', 'min:0'],
            'prd_stkmin' => ['required', 'integer', 'min:0'],
            'prd_estado' => ['required', 'integer', 'in:0,1'],

            'imagenes'   => ['sometimes', 'array'],
            'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
