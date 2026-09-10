<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('rol');

        return [
            'rol_descri' => [
                'required', 'string', 'max:45',
                Rule::unique('rol', 'rol_descri')->ignore($id, 'rol_id'),
            ],
            'rol_estado' => ['nullable', 'integer', 'in:0,1'],

            // Checklist de permisos: array de IDs de la tabla permiso.
            'permisos'   => ['nullable', 'array'],
            'permisos.*' => ['integer', 'exists:permiso,prm_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'rol_descri.required' => 'El nombre del rol es obligatorio.',
            'rol_descri.unique'   => 'Ya existe un rol con ese nombre.',
            'permisos.*.exists'   => 'Uno de los permisos seleccionados no existe.',
        ];
    }
}