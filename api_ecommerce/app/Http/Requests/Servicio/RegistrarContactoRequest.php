<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\ServicioHistorialEstado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Paso 2 - "Registrar contacto con el cliente" (she_tipo CONTACTO). */
class RegistrarContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medio'     => ['required', Rule::in(ServicioHistorialEstado::MEDIOS)],
            'resultado' => ['required', Rule::in(ServicioHistorialEstado::RESULTADOS)],
            'obs'       => ['nullable', 'required_if:resultado,NO_CONTESTA', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'medio.required'     => 'Indique el medio de contacto.',
            'resultado.required' => 'Indique el resultado del contacto.',
            'obs.required_if'    => 'Si el cliente no contestó, deje una observación (hora, número usado, etc.).',
            'obs.max'            => 'La observación no puede superar los 300 caracteres.',
        ];
    }
}
