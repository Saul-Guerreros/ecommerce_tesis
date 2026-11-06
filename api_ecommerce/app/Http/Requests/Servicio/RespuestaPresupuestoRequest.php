<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\PresupuestoServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Paso 2 - Respuesta del cliente registrada por un funcionario (aprobación asistida). */
class RespuestaPresupuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APROBAR', 'RECHAZAR'])],
            'canal'    => ['required', Rule::in(PresupuestoServicio::RESPUESTAS_ASISTIDAS)],
            // La observación es la evidencia de la respuesta (decisión A.2): siempre obligatoria.
            'obs'      => ['required', 'string', 'min:10', 'max:200'],
            'motivo'   => ['nullable', 'required_if:decision,RECHAZAR', 'string', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Indique si el cliente aprobó o rechazó.',
            'canal.required'    => 'Indique por qué medio respondió el cliente.',
            'canal.in'          => 'El medio de respuesta no es válido.',
            'obs.required'      => 'La observación es obligatoria (respaldo de la respuesta del cliente).',
            'obs.min'           => 'Detalle un poco más la observación (mínimo 10 caracteres).',
            'obs.max'           => 'La observación no puede superar los 200 caracteres.',
            'motivo.required_if' => 'Indique el motivo del rechazo.',
            'motivo.min'        => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max'        => 'El motivo no puede superar los 200 caracteres.',
        ];
    }
}
