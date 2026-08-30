<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorizacion real la hace el middleware 'permiso:usuarios.gestionar' en la ruta.
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre1' => ['required', 'string', 'max:45'],
            'nombre2' => ['nullable', 'string', 'max:45'],
            'apellido1' => ['required', 'string', 'max:50'],
            'apellido2' => ['nullable', 'string', 'max:50'],
            'cedula' => ['required', 'string', 'max:50', 'unique:persona,per_cedula'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'cargo_id' => ['required', 'integer', 'exists:cargo,crg_id'],
            'rol_id' => ['required', 'integer', 'exists:rol,rol_id'],
            'email' => ['required', 'email', 'unique:usuario,usu_login'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
