<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ncr_id'      => $this->ncr_id,
            'ncr_comid'   => $this->ncr_comid,
            'ncr_numero'  => $this->ncr_numero,
            'ncr_fecha'   => $this->ncr_fecha?->format('Y-m-d'),
            'ncr_motivo'  => $this->ncr_motivo,
            'ncr_exenta'  => $this->ncr_exenta,
            'ncr_grav5'   => $this->ncr_grav5,
            'ncr_grav10'  => $this->ncr_grav10,
            'ncr_iva5'    => $this->ncr_iva5,
            'ncr_iva10'   => $this->ncr_iva10,
            'ncr_gravada' => $this->ncr_gravada,
            'ncr_iva'     => $this->ncr_iva,
            'ncr_total'   => $this->ncr_total,
            'ncr_obs'     => $this->ncr_obs,
            'ncr_creado'  => $this->ncr_creado,
            'detalle'     => DetNotaCreditoResource::collection($this->whenLoaded('detalle')),
        ];
    }
}