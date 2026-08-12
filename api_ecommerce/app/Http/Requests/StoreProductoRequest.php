<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta según tu lógica de permisos/roles
    }

    public function rules(): array
    {
        return [
            'prd_marid'  => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_catid'  => ['required', 'integer', 'exists:categoria,cat_id'],
            'prd_titulo' => ['required', 'string', 'max:45'],
            'prd_codinv' => ['required', 'string', 'max:45', 'unique:producto,prd_codinv'],
            'prd_descri' => ['required', 'string'],
            'prd_precio' => ['required', 'numeric', 'min:0'],
            'prd_stock'  => ['required', 'integer', 'min:0'],
            'prd_stkmin' => ['required', 'integer', 'min:0'],
            'prd_estado' => ['required', 'integer', 'in:0,1'],

            // imágenes opcionales al crear
            'imagenes'   => ['sometimes', 'array'],
            'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'prd_marid.exists' => 'La marca seleccionada no existe.',
            'prd_catid.exists' => 'La categoría seleccionada no existe.',
            'prd_codinv.unique' => 'Ya existe un producto con ese código de inventario.',
        ];
    }
}
