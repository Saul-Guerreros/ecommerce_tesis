<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('marca');

        return [
            'mar_descri' => [
                'required',
                'string',
                'max:50',
                Rule::unique('marca', 'mar_descri')->ignore($id, 'mar_id'),
            ],
            'mar_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'mar_descri.required' => 'El nombre de la marca es obligatorio.',
            'mar_descri.unique'   => 'Ya existe una marca con ese nombre.',
            'mar_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }
}
