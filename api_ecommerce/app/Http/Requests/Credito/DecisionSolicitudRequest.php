<?php

namespace App\Http\Requests\Credito;

use App\Models\Credito\SolicitudCredito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * [GUARDAR DECISION] de la pestaña Evaluacion.
 *
 * Un solo Request para aprobar y rechazar, porque es un solo formulario
 * y un solo endpoint: los campos que no aplican se vuelven obligatorios
 * o prohibidos segun la decision, con required_if / prohibited_unless.
 * Dos Requests separados obligarian a duplicar las observaciones.
 */
class DecisionSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['APROBAR', 'RECHAZAR'])],

            'observaciones' => ['nullable', 'string', 'max:500'],

            // --- Solo al aprobar ---
            'vigencia_dias' => [
                'required_if:decision,APROBAR',
                'prohibited_unless:decision,APROBAR',
                'integer', 'min:1', 'max:60',
            ],

            // --- Solo al rechazar ---
            'motivo' => [
                'required_if:decision,RECHAZAR',
                'prohibited_unless:decision,RECHAZAR',
                Rule::in(array_keys(SolicitudCredito::MOTIVOS_RECHAZO)),
            ],

            // Obligatorio solo cuando el motivo es "Otro": si no se
            // explica, el rechazo queda sin fundamento para el cliente.
            'detalle' => [
                'nullable',
                'required_if:motivo,OTRO',
                'string', 'max:300',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Elegí si la solicitud se aprueba o se rechaza.',
            'decision.in'       => 'La decisión indicada no es válida.',

            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',

            'vigencia_dias.required_if'     => 'Indicá cuántos días estará vigente la oferta.',
            'vigencia_dias.prohibited_unless' => 'La vigencia solo aplica a una solicitud aprobada.',
            'vigencia_dias.min'             => 'La vigencia debe ser de al menos 1 día.',
            'vigencia_dias.max'             => 'La vigencia no puede superar los 60 días.',

            'motivo.required_if'       => 'Seleccioná el motivo del rechazo.',
            'motivo.prohibited_unless' => 'El motivo solo aplica a una solicitud rechazada.',
            'motivo.in'                => 'El motivo de rechazo seleccionado no es válido.',

            'detalle.required_if' => 'Si el motivo es "Otro", detallá la razón del rechazo.',
            'detalle.max'         => 'El detalle no puede superar los 300 caracteres.',
        ];
    }
}