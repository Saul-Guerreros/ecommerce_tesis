<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SucursalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('sucursal');

        return [
            'suc_empid' => ['required', 'integer', 'exists:empresa,emp_id'],
            'suc_ciuid' => ['required', 'integer', 'exists:ciudad,ciu_id'],
            'suc_nombre' => [
                'required',
                'string',
                'max:50',
                // UNIQUE compuesto (suc_empid, suc_nombre): el nombre solo debe
                // ser unico dentro de la misma empresa.
                Rule::unique('sucursal', 'suc_nombre')
                    ->where(fn ($q) => $q->where('suc_empid', $this->input('suc_empid')))
                    ->ignore($id, 'suc_id'),
            ],
            'suc_princi' => ['nullable', 'integer', 'in:0,1'],
            'suc_establ' => [
                'nullable',
                'regex:/^[0-9]{3}$/',
                // UNIQUE compuesto (suc_empid, suc_establ): dos sucursales de
                // la misma empresa no pueden compartir codigo.
                Rule::unique('sucursal', 'suc_establ')
                    ->where(fn ($q) => $q->where('suc_empid', $this->input('suc_empid')))
                    ->ignore($id, 'suc_id'),
            ],
            'suc_direccion' => ['nullable', 'string', 'max:100'],
            'suc_telef'     => ['nullable', 'string', 'max:20'],
            'suc_cel'       => ['nullable', 'string', 'max:20'],
            'suc_email'     => ['nullable', 'email', 'max:60'],
            'suc_estado'    => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Regla de negocio que depende del conjunto de datos (no de un solo
     * campo): si se marca esta sucursal como principal, no puede haber ya
     * otra sucursal activa marcada como principal en la misma empresa.
     * El UNIQUE parcial de la migracion es la ultima linea de defensa;
     * esto solo mejora el mensaje que ve el usuario en vez de un 500.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $marcaComoPrincipal = (int) $this->input('suc_princi', 0) === 1;

            if (!$marcaComoPrincipal) {
                return;
            }

            $id = $this->route('sucursal');

            $yaExiste = DB::table('sucursal')
                ->where('suc_empid', $this->input('suc_empid'))
                ->where('suc_princi', 1)
                ->where('suc_estado', 1)
                ->when($id, fn ($q) => $q->where('suc_id', '!=', $id))
                ->exists();

            if ($yaExiste) {
                $validator->errors()->add(
                    'suc_princi',
                    'Esta empresa ya tiene una sucursal principal activa. Desmarquela antes de asignar otra.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'suc_empid.required'   => 'Debe seleccionar la empresa.',
            'suc_empid.exists'     => 'La empresa seleccionada no existe.',
            'suc_ciuid.required'   => 'Debe seleccionar la ciudad.',
            'suc_ciuid.exists'     => 'La ciudad seleccionada no existe.',
            'suc_nombre.required'  => 'El nombre de la sucursal es obligatorio.',
            'suc_nombre.unique'    => 'Esa empresa ya tiene una sucursal con ese nombre.',
            'suc_nombre.max'       => 'El nombre no puede superar los 50 caracteres.',
            'suc_establ.regex'     => 'El codigo de establecimiento debe tener exactamente 3 digitos (ej: 001).',
            'suc_establ.unique'    => 'Esa empresa ya tiene una sucursal con ese codigo de establecimiento.',
            'suc_email.email'      => 'El correo electronico no tiene un formato valido.',
        ];
    }
}