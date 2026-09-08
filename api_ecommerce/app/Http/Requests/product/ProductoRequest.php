<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * prd_stkmin (SI es valido: cantidad minima antes de alertar reposicion)
     * pero NO stock_actual: ese campo nunca se acepta desde este Request,
     * aunque el frontend lo mande. El stock real se gestiona solo desde
     * CompraService (movimiento_stock).
     */
    public function rules(): array
    {
        $id = $this->route('producto');

        return [
            'prd_marid' => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_tivid' => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'prd_umdid' => ['required', 'integer', 'exists:unidad_medida,umd_id'],
            'prd_codinv' => [
                'required',
                'string',
                'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($id, 'prd_id'),
            ],
            'prd_descri' => ['required', 'string', 'max:150'],
            'prd_prevta' => ['required', 'numeric', 'min:0'],
            'prd_margen' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'prd_stkmin' => ['required', 'numeric', 'min:0'],
            'prd_afestk' => ['nullable', 'integer', 'in:0,1'],
            'prd_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'prd_marid.required'  => 'Debe seleccionar la marca.',
            'prd_marid.exists'    => 'La marca seleccionada no existe.',
            'prd_tivid.required'  => 'Debe seleccionar el tipo de IVA.',
            'prd_tivid.exists'    => 'El tipo de IVA seleccionado no existe.',
            'prd_umdid.required'  => 'Debe seleccionar la unidad de medida.',
            'prd_umdid.exists'    => 'La unidad de medida seleccionada no existe.',
            'prd_codinv.required' => 'El código de inventario es obligatorio.',
            'prd_codinv.unique'   => 'Ya existe un producto con ese código de inventario.',
            'prd_descri.required' => 'El nombre del producto es obligatorio.',
            'prd_descri.max'      => 'El nombre no puede superar los 150 caracteres.',
            'prd_prevta.required' => 'El precio de venta es obligatorio.',
            'prd_prevta.numeric'  => 'El precio de venta debe ser un número.',
            'prd_prevta.min'      => 'El precio de venta no puede ser negativo.',
            'prd_margen.numeric'  => 'El margen debe ser un número.',
            'prd_margen.min'      => 'El margen no puede ser negativo.',
            'prd_margen.max'      => 'El margen ingresado es demasiado grande.',
            'prd_stkmin.required' => 'El stock mínimo es obligatorio.',
            'prd_stkmin.min'      => 'El stock mínimo no puede ser negativo.',
        ];
    }
}