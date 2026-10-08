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
                Rule::unique('sucursal', 'suc_nombre')
                    ->where(fn ($q) => $q->where('suc_empid', $this->input('suc_empid')))
                    ->ignore($id, 'suc_id'),
            ],
            'suc_princi' => ['nullable', 'integer', 'in:0,1'],
            'suc_establ' => [
                'nullable',
                'regex:/^[0-9]{3}$/',
                Rule::unique('sucursal', 'suc_establ')
                    ->where(fn ($q) => $q->where('suc_empid', $this->input('suc_empid')))
                    ->ignore($id, 'suc_id'),
            ],
            'suc_direccion' => ['nullable', 'string', 'max:100'],
            'suc_telef'     => ['nullable', 'string', 'max:20'],
            'suc_cel'       => ['nullable', 'string', 'max:20'],
            'suc_email'     => ['nullable', 'email', 'max:60'],
            'suc_estado'    => ['nullable', 'integer', 'in:0,1'],
            'suc_retiro'    => ['nullable', 'boolean'],              // <-- nuevo
            'suc_horario'   => ['nullable', 'string', 'max:150'],    // <-- nuevo
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $marcaComoPrincipal = (int) $this->input('suc_princi', 0) === 1;

            if ($marcaComoPrincipal) {
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
            }

            // Una sucursal habilitada para retiro necesita dirección: es el
            // dato que el cliente ve en el Checkout - Paso 1.
            if ($this->boolean('suc_retiro') && blank($this->input('suc_direccion'))) {
                $validator->errors()->add(
                    'suc_direccion',
                    'Una sucursal habilitada para retiro necesita dirección.'
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