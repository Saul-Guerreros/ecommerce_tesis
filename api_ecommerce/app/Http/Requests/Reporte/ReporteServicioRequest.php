<?php

namespace App\Http\Requests\Reporte;

/** Filtros comunes + propios del reporte de servicio técnico. */
class ReporteServicioRequest extends FiltroReporteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tipo_dispositivo_id' => ['nullable', 'integer', 'exists:tipo_dispositivo,tdi_id'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'tipo_dispositivo_id.exists' => 'El tipo de dispositivo seleccionado no existe.',
        ];
    }

    public function tipoDispositivoId(): ?int
    {
        return $this->filled('tipo_dispositivo_id') ? (int) $this->input('tipo_dispositivo_id') : null;
    }
}
