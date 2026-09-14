<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoAjusteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('tipo_ajuste');

        return [
            'tpa_codig'  => ['required', 'string', 'max:10',
                Rule::unique('tipo_ajuste', 'tpa_codig')->ignore($id, 'tpa_id')],
            'tpa_descri' => ['required', 'string', 'max:60'],
            'tpa_natura' => ['required', 'in:P,N'],
            'tpa_reqcom' => ['nullable', 'boolean'],
            'tpa_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tpa_codig.required'  => 'El código es obligatorio.',
            'tpa_codig.unique'    => 'Ya existe un tipo de ajuste con ese código.',
            'tpa_codig.max'       => 'El código no puede superar los 10 caracteres.',
            'tpa_descri.required' => 'La descripción es obligatoria.',
            'tpa_descri.max'      => 'La descripción no puede superar los 60 caracteres.',
            'tpa_natura.required' => 'Debe indicar la naturaleza del ajuste.',
            'tpa_natura.in'       => 'La naturaleza debe ser Positivo (P) o Negativo (N).',
        ];
    }
}