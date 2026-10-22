<?php

namespace App\Http\Requests\Reporte;

/** Filtros comunes + propios del reporte de clientes. */
class ReporteClientesRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tipo_cliente_id' => ['nullable', 'integer', 'exists:tipo_cliente,tpc_id'],
            'dias'            => ['nullable', 'integer', 'min:30', 'max:1095'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'tipo_cliente_id.exists' => 'El tipo de cliente seleccionado no existe.',
            'dias.min'               => 'El umbral mínimo de inactividad es de 30 días.',
            'dias.max'               => 'El umbral máximo de inactividad es de 1095 días (3 años).',
        ];
    }

    public function tipoClienteId(): ?int
    {
        return $this->filled('tipo_cliente_id') ? (int) $this->input('tipo_cliente_id') : null;
    }

    /** Días sin comprar para considerar inactivo a un cliente. */
    public function dias(): int
    {
        return (int) $this->input('dias', 90);
    }
}
