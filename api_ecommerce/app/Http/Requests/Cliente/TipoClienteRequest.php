<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // El parámetro se llama {id} (ver ->parameters() en routes/api.php)
        $id = $this->route('id');

        return [
            'tpc_descri' => [
                'required', 'string', 'max:50',
                Rule::unique('tipo_cliente', 'tpc_descri')->ignore($id, 'tpc_id'),
            ],
            'tpc_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tpc_descri')) {
            $this->merge(['tpc_descri' => trim((string) $this->input('tpc_descri'))]);
        }
    }

    public function messages(): array
    {
        return [
            'tpc_descri.required' => 'La descripción es obligatoria.',
            'tpc_descri.max'      => 'La descripción no puede superar los 50 caracteres.',
            'tpc_descri.unique'   => 'Ya existe un tipo de cliente con esa descripción.',
            'tpc_estado.in'       => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
