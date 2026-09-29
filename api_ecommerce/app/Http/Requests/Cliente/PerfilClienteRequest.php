<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clienteId = $this->user('api')?->persona?->cliente?->cli_id;

        return [
            'per_nombr1' => ['required', 'string', 'max:45'],
            'per_nombr2' => ['nullable', 'string', 'max:45'],
            'per_apell1' => ['required', 'string', 'max:50'],
            'per_apell2' => ['nullable', 'string', 'max:50'],
            'per_fechna' => ['nullable', 'date', 'before:today'],

            'celular' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],

            'cli_ruc' => [
                'nullable', 'string', 'max:45', 'regex:/^\d{1,15}-\d$/',
                Rule::unique('cliente', 'cli_ruc')->ignore($clienteId, 'cli_id'),
            ],

            // max:2048 KB = 2MB
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_nombr1.required' => 'El primer nombre es obligatorio.',
            'per_apell1.required' => 'El primer apellido es obligatorio.',
            'per_fechna.before'   => 'La fecha de nacimiento debe ser anterior a hoy.',
            'celular.regex'       => 'El número de celular no tiene un formato válido.',
            'cli_ruc.regex'       => 'El RUC debe tener el formato 1234567-8.',
            'cli_ruc.unique'      => 'Ese RUC ya está registrado por otro cliente.',
            'avatar.image'        => 'El archivo debe ser una imagen.',
            'avatar.max'          => 'La imagen no puede superar los 2MB.',
        ];
    }
}