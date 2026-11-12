<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // En PUT /tipos-proveedor/{tipos_proveedor} (apiResource) se ignora el propio registro en el unique.
        $id = $this->route('tipos_proveedor');

        return [
            'tpr_descri' => [
                'required', 'string', 'max:45',
                Rule::unique('tipo_proveedor', 'tpr_descri')->ignore($id, 'tpr_id'),
            ],
            'tpr_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tpr_descri.required' => 'La descripción es obligatoria.',
            'tpr_descri.max'      => 'La descripción admite hasta 45 caracteres.',
            'tpr_descri.unique'   => 'Ya existe un tipo de proveedor con esa descripción.',
            'tpr_estado.in'       => 'El estado no es válido.',
        ];
    }
}