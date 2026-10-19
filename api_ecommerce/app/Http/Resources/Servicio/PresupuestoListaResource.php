<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fila de la pantalla de validación de presupuestos (admin). */
class PresupuestoListaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->orden?->solicitud;

        return [
            'pse_id'     => $this->pse_id,
            'pse_numero' => $this->pse_numero,
            'pse_versio' => $this->pse_versio,
            'estado'     => ['codigo' => $this->estado?->epr_codigo, 'descri' => $this->estado?->epr_descri],
            'pse_total'  => (float) $this->pse_total,
            'pse_creado' => $this->pse_creado?->format('Y-m-d H:i'),
            'ose_numero' => $this->orden?->ose_numero,
            'sse_numero' => $s?->sse_numero,
            'cliente'    => $s?->cliente?->persona?->nombre_completo,
            'equipo'     => $s ? trim(($s->tipoDispositivo?->tdi_descri ?? '') . ' ' . $s->sse_marca . ' ' . $s->sse_modelo) : null,
            'tecnico'    => $this->tecnico?->persona?->nombre_completo,
        ];
    }
}
