<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('departamento');

        return [
            'dep_descri' => [
                'required',
                'string',
                'max:50',
                // UNIQUE simple: no hay dos departamentos con el mismo nombre
                // en todo el pais, a diferencia de ciudad que usa clave compuesta.
                Rule::unique('departamento', 'dep_descri')->ignore($id, 'dep_id'),
            ],
            'dep_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'dep_descri.required' => 'El nombre del departamento es obligatorio.',
            'dep_descri.unique'   => 'Ya existe un departamento con ese nombre.',
            'dep_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }
}
