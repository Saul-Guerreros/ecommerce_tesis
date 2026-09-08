<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class ContactoProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cpr_proid'    => ['required', 'integer', 'exists:proveedor,pro_id'],
            'cpr_nombre'   => ['required', 'string', 'max:60'],
            'cpr_apellido' => ['required', 'string', 'max:60'],
            'cpr_cargo'    => ['nullable', 'string', 'max:40'],
            'cpr_telef'    => ['nullable', 'string', 'max:20'],
            'cpr_email'    => ['nullable', 'email', 'max:80'],
            'cpr_princi'   => ['nullable', 'integer', 'in:0,1'],
            'cpr_estado'   => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Mismo criterio que suc_princi/dpo_princi: no puede haber dos contactos
     * activos marcados como principal para el mismo proveedor.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((int) $this->input('cpr_princi', 0) !== 1) {
                return;
            }

            $id = $this->route('contacto_proveedor');

            $yaExiste = DB::table('contacto_proveedor')
                ->where('cpr_proid', $this->input('cpr_proid'))
                ->where('cpr_princi', 1)
                ->where('cpr_estado', 1)
                ->when($id, fn ($q) => $q->where('cpr_id', '!=', $id))
                ->exists();

            if ($yaExiste) {
                $validator->errors()->add(
                    'cpr_princi',
                    'Este proveedor ya tiene un contacto principal activo. Desmárquelo antes de asignar otro.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'cpr_proid.required'    => 'Falta indicar el proveedor.',
            'cpr_proid.exists'      => 'El proveedor indicado no existe.',
            'cpr_nombre.required'   => 'El nombre es obligatorio.',
            'cpr_apellido.required' => 'El apellido es obligatorio.',
            'cpr_email.email'       => 'El correo electrónico no tiene un formato válido.',
        ];
    }
}