<?php

namespace App\Http\Requests\Perfil;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\FechaNacimiento;

class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_nombr1' => ['required', 'string', 'max:45'],
            'per_nombr2' => ['nullable', 'string', 'max:45'],
            'per_apell1' => ['required', 'string', 'max:50'],
            'per_apell2' => ['nullable', 'string', 'max:50'],
            'per_fechna' => ['nullable', new FechaNacimiento()],
            'celular'    => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'avatar'     => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_nombr1.required' => 'El primer nombre es obligatorio.',
            'per_apell1.required' => 'El primer apellido es obligatorio.',
            'celular.regex'       => 'El número de celular no tiene un formato válido.',
            'avatar.image'        => 'El archivo debe ser una imagen.',
            'avatar.mimes'        => 'Formatos permitidos: jpg, png, webp.',
            'avatar.max'          => 'La imagen no puede superar los 2 MB.',
        ];
    }
}