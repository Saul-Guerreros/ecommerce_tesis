<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('proveedor');

        return [
            'pro_tprid' => ['required', 'integer', 'exists:tipo_proveedor,tpr_id'],
            'pro_ciuid' => ['required', 'integer', 'exists:ciudad,ciu_id'],
            'pro_ruc' => [
                'required', 'string', 'max:15',
                Rule::unique('proveedor', 'pro_ruc')->ignore($id, 'pro_id'),
            ],
            'pro_razon'     => ['required', 'string', 'max:150'],
            'pro_nomfan'    => ['nullable', 'string', 'max:80'],
            'pro_direccion' => ['nullable', 'string', 'max:100'],
            'pro_telef'     => ['nullable', 'string', 'max:20'],
            'pro_email'     => ['nullable', 'email', 'max:60'],
            'pro_estado'    => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'pro_tprid.required' => 'Debe seleccionar el tipo de proveedor.',
            'pro_tprid.exists'   => 'El tipo de proveedor seleccionado no existe.',
            'pro_ciuid.required' => 'Debe seleccionar la ciudad.',
            'pro_ciuid.exists'   => 'La ciudad seleccionada no existe.',
            'pro_ruc.required'   => 'El RUC es obligatorio.',
            'pro_ruc.unique'     => 'Ya existe un proveedor registrado con ese RUC.',
            'pro_razon.required' => 'La razón social es obligatoria.',
            'pro_razon.max'      => 'La razón social no puede superar los 150 caracteres.',
            'pro_email.email'    => 'El correo electrónico no tiene un formato válido.',
        ];
    }
}