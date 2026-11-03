<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class PaginaInformativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso lo controla el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'pgi_titulo'    => ['required', 'string', 'max:150'],
            'pgi_contenido' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'pgi_titulo.required'    => 'El título es obligatorio.',
            'pgi_contenido.required' => 'El contenido es obligatorio.',
        ];
    }
}