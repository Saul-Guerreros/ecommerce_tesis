<?php

namespace App\Http\Requests\Chat;

use App\Models\Chat\FaqTienda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // el permiso lo controla el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'fqt_tema' => ['required', Rule::in(array_keys(FaqTienda::TEMAS))],
            'fqt_pregunta' => [
                'required', 'string', 'max:255',
                // La misma pregunta no se repite dentro de un tema.
                Rule::unique('faq_tienda', 'fqt_pregunta')
                    ->where('fqt_tema', $this->input('fqt_tema'))
                    ->ignore($this->route('id'), 'fqt_id'),
            ],
            'fqt_respuesta' => ['required', 'string', 'max:1000'],
            'fqt_orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'fqt_activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fqt_tema.required' => 'Seleccioná un tema.',
            'fqt_tema.in' => 'El tema seleccionado no es válido.',
            'fqt_pregunta.required' => 'La pregunta es obligatoria.',
            'fqt_pregunta.unique' => 'Ya existe esa pregunta en este tema.',
            'fqt_respuesta.required' => 'La respuesta es obligatoria.',
            'fqt_respuesta.max' => 'La respuesta puede tener como máximo 1000 caracteres.',
        ];
    }
}