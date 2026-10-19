<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pse_id'     => $this->pse_id,
            'pse_numero' => $this->pse_numero,
            'pse_versio' => $this->pse_versio,
            'estado'     => ['codigo' => $this->estado?->epr_codigo, 'descri' => $this->estado?->epr_descri],
            'pse_subtot' => (float) $this->pse_subtot,
            'pse_impues' => (float) $this->pse_impues,
            'pse_total'  => (float) $this->pse_total,
            'pse_fecven' => $this->pse_fecven?->format('Y-m-d'),
            'pse_obs'    => $this->pse_obs,
            'pse_obsval' => $this->pse_obsval,
            'pse_motrec' => $this->pse_motrec,
            'pse_enviad' => $this->pse_enviad?->format('Y-m-d H:i'),
            'pse_respon' => $this->pse_respon?->format('Y-m-d H:i'),
            'detalles'   => PresupuestoDetalleResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
