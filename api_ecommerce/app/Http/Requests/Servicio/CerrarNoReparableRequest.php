<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class CerrarNoReparableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cobrar_revision' => filter_var($this->input('cobrar_revision', false), FILTER_VALIDATE_BOOLEAN)]);
    }

    public function rules(): array
    {
        return [
            'cobrar_revision' => ['required', 'boolean'],
            'ose_proced'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['ose_proced.max' => 'El detalle no puede superar los 2000 caracteres.'];
    }
}
