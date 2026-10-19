<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class ReasignarTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rse_tecid' => ['required', 'integer'],
            'motivo'    => ['required', 'string', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'rse_tecid.required' => 'Seleccione el nuevo técnico.',
            'motivo.required'    => 'Indique el motivo de la reasignación.',
            'motivo.min'         => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max'         => 'El motivo no puede superar los 200 caracteres.',
        ];
    }
}
