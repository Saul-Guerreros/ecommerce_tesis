<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = $this->route('usuario');

        return [
            'nombre1' => ['required', 'string', 'max:45'],
            'nombre2' => ['nullable', 'string', 'max:45'],
            'apellido1' => ['required', 'string', 'max:50'],
            'apellido2' => ['nullable', 'string', 'max:50'],
            'cedula' => [
                'required', 'string', 'max:50',
                Rule::unique('persona', 'per_cedula')->ignore($usuario->usu_perid, 'per_id'),
            ],
            'fecha_nacimiento' => ['nullable', 'date'],
            'cargo_id' => ['required', 'integer', 'exists:cargo,crg_id'],
            'rol_id' => ['required', 'integer', 'exists:rol,rol_id'],
            'email' => [
                'required', 'email',
                Rule::unique('usuario', 'usu_login')->ignore($usuario->usu_id, 'usu_id'),
            ],
            // La contrasena es opcional al editar: si viene vacia, no se toca.
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
