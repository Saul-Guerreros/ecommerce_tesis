<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Rules\FechaNacimiento;

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
            'fecha_nacimiento' => ['nullable', new FechaNacimiento()],
            'cargo_id' => ['required', 'integer', 'exists:cargo,crg_id'],
            'rol_id' => ['required', 'integer', 'exists:rol,rol_id'],
            'email' => ['required', 'email', 'unique:usuario,usu_login'],
            'password' => ['required', 'string', 'min:8'],

            // Sucursales asignadas: al menos una, y todas deben existir.
            'sucursales'          => ['required', 'array', 'min:1'],
            'sucursales.*'        => ['integer', 'distinct', 'exists:sucursal,suc_id'],
            // Cual de las anteriores es la principal (solo se exige/valida
            // si hay mas de una -- con una sola, es principal automatico).
            'sucursal_principal'  => ['nullable', 'integer'],
        ];
    }

    /**
     * sucursal_principal, si viene, tiene que ser una de las IDs incluidas
     * en 'sucursales' -- no tiene sentido marcar como principal algo que
     * no esta en la lista de asignadas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sucursales = (array) $this->input('sucursales', []);
            $principal  = $this->input('sucursal_principal');

            if ($principal !== null && !in_array((int) $principal, array_map('intval', $sucursales), true)) {
                $validator->errors()->add(
                    'sucursal_principal',
                    'La sucursal principal debe ser una de las sucursales asignadas.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'sucursales.required' => 'Debe asignar al menos una sucursal.',
            'sucursales.min'      => 'Debe asignar al menos una sucursal.',
            'sucursales.*.distinct' => 'No puede repetir la misma sucursal.',
            'sucursales.*.exists' => 'Una de las sucursales seleccionadas no existe.',
        ];
    }
}







/*
Anterior Código antes de agregar Usuario_Sucursal
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
*/