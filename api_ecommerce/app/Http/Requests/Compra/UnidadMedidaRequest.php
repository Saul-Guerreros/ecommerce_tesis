<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('unidad_medida');

        return [
            'umd_codigo' => [
                'required',
                'string',
                'max:5',
                Rule::unique('unidad_medida', 'umd_codigo')->ignore($id, 'umd_id'),
            ],
            'umd_nombre'  => ['required', 'string', 'max:40'],
            'umd_decimal' => ['nullable', 'integer', 'in:0,1'],
            'umd_estado'  => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'umd_codigo.required' => 'El código es obligatorio.',
            'umd_codigo.unique'   => 'Ya existe una unidad con ese código.',
            'umd_codigo.max'      => 'El código no puede superar los 5 caracteres.',
            'umd_nombre.required' => 'El nombre es obligatorio.',
            'umd_nombre.max'      => 'El nombre no puede superar los 40 caracteres.',
        ];
    }
}