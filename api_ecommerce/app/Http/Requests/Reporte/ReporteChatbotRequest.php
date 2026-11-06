<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Validation\Rule;

/** Filtros comunes + propios del reporte del asistente virtual. */
class ReporteChatbotRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tipo' => ['nullable', Rule::in(['todos', 'sin_respuesta', 'derivada'])],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'tipo.in' => 'El tipo debe ser todos, sin respuesta o derivada.',
        ];
    }

    /** Filtro del detalle de consultas no resueltas. */
    public function tipo(): string
    {
        return $this->input('tipo', 'todos');
    }
}
