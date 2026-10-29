<?php

namespace App\Http\Requests\Reporte;

use App\Models\Comportamiento\Busqueda;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Validation\Rule;

/** Filtros comunes + propios del análisis de comportamiento. */
class ReporteComportamientoRequest extends FiltroReporteRequest
{
    public const MESES = [3, 6, 12, 24];

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'origen'     => ['nullable', Rule::in([Busqueda::ORIGEN_TIENDA, Busqueda::ORIGEN_CHATBOT])],
            'horas'      => ['nullable', 'integer', 'min:1', 'max:720'],
            'meses'      => ['nullable', 'integer', Rule::in(self::MESES)],
            'q'          => ['nullable', 'string', 'min:2', 'max:60'],
            'cliente_id' => ['nullable', 'integer', 'exists:cliente,cli_id'],
            'forzar'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'origen.in'         => 'El origen debe ser Tienda o Chatbot.',
            'horas.min'         => 'El umbral mínimo de abandono es de 1 hora.',
            'horas.max'         => 'El umbral máximo de abandono es de 720 horas (30 días).',
            'meses.in'          => 'El período debe ser de 3, 6, 12 o 24 meses.',
            'q.min'             => 'Escribí al menos 2 caracteres.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
        ];
    }

    public function origen(): ?string
    {
        return $this->input('origen') ?: null;
    }

    public function horas(): int
    {
        return (int) $this->input('horas', 48);
    }

    public function clienteId(): ?int
    {
        return $this->filled('cliente_id') ? (int) $this->input('cliente_id') : null;
    }

    public function forzar(): bool
    {
        return $this->boolean('forzar');
    }

    /**
     * Segmentos y perfil necesitan historia: un mes no alcanza para medir
     * recencia ni frecuencia. Usan los últimos N meses hasta hoy.
     */
    public function filtrosHistoricos(): FiltrosReporte
    {
        $meses = (int) $this->input('meses', 12);

        return new FiltrosReporte(
            desde: now()->subMonthsNoOverflow($meses)->startOfDay(),
            hasta: now()->endOfDay(),
            sucursalId: $this->filled('sucursal_id') ? (int) $this->input('sucursal_id') : null,
            limite: (int) $this->input('limite', 10),
        );
    }
}