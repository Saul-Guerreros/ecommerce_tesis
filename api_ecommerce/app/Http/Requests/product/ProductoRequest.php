<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('producto');

        return [
            'prd_marid'  => ['required', 'integer', 'exists:marca,mar_id'],
            'prd_tivid'  => ['required', 'integer', 'exists:tipo_iva,tiv_id'],
            'prd_umdid'  => ['required', 'integer', 'exists:unidad_medida,umd_id'],
            'prd_codinv' => [
                'required', 'string', 'max:45',
                Rule::unique('producto', 'prd_codinv')->ignore($id, 'prd_id'),
            ],
            'prd_descri' => ['required', 'string', 'max:150'],
            'prd_stkmin' => ['required', 'numeric', 'min:0'],
            'prd_afestk' => ['nullable', 'integer', 'in:0,1'],
            'prd_estado' => ['nullable', 'integer', 'in:0,1'],

            // Equivalencia inicial (solo alta, ver ProductoController::store).
            // No son columnas de "producto": si tiene_equiv_compra es falso
            // o no viene, no se exige nada.
            'tiene_equiv_compra' => ['nullable', 'boolean'],
            'peq_umdid' => [
                'nullable', 'integer', 'exists:unidad_medida,umd_id',
                'required_if:tiene_equiv_compra,1',
            ],
            'peq_factor' => [
                'nullable', 'numeric', 'min:0.0001',
                'required_if:tiene_equiv_compra,1',
            ],
        ];
    }

    /**
     * La unidad alternativa de compra no puede ser igual a la unidad de
     * stock del producto (esa ya es la base, con factor 1 implícito).
     * Misma regla que ProductoEquivalenciaRequest, pero comparando contra
     * prd_umdid del propio payload (el producto todavía no existe en alta).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->boolean('tiene_equiv_compra')) {
                return;
            }

            if ($this->filled('prd_umdid') && $this->filled('peq_umdid')
                && (int) $this->prd_umdid === (int) $this->peq_umdid) {
                $validator->errors()->add(
                    'peq_umdid',
                    'La unidad alternativa no puede ser la misma unidad de stock del producto.'
                );
            }
        });
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
            'prd_stkmin.required' => 'El stock mínimo es obligatorio.',
            'prd_stkmin.min'      => 'El stock mínimo no puede ser negativo.',
            'peq_umdid.required_if'  => 'Debe seleccionar la unidad alternativa.',
            'peq_umdid.exists'       => 'La unidad alternativa seleccionada no existe.',
            'peq_factor.required_if' => 'Debe indicar la equivalencia.',
            'peq_factor.min'         => 'La equivalencia debe ser mayor a 0.',
        ];
    }
}