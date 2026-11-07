<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;

/** Respuesta pública de la tienda. Vacía = quitar la respuesta. */
class ResponderResenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware permiso:resenas.gestionar
    }

    protected function prepareForValidation(): void
    {
        $texto = $this->input('res_respue');

        if (is_string($texto)) {
            $texto = strip_tags(str_replace("\r", '', $texto));
            $texto = trim(preg_replace(["/[ \t]+/u", "/\n{3,}/"], [' ', "\n\n"], $texto));
        }

        $this->merge(['res_respue' => is_string($texto) && $texto !== '' ? $texto : null]);
    }

    public function rules(): array
    {
        return [
            'res_respue' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'res_respue.max' => 'La respuesta admite hasta 1000 caracteres.',
        ];
    }
}