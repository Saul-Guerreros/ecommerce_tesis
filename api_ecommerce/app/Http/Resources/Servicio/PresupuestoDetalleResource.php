<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'psd_id'     => $this->psd_id,
            'psd_tipo'   => $this->psd_tipo,
            'psd_prdid'  => $this->psd_prdid,
            'psd_trbid'  => $this->psd_trbid,
            'psd_descri' => $this->psd_descri,
            'psd_cantid' => (float) $this->psd_cantid,
            'psd_precio' => (float) $this->psd_precio,
            'psd_iva'    => (float) $this->psd_iva,
            'psd_total'  => (float) $this->psd_total,
            'psd_reserv' => $this->psd_reserv,
            // Solo los cargan el técnico y el validador (no el cliente)
            'precio_lista' => $this->whenHas('precio_lista'),
            'disponible'   => $this->whenHas('disponible'),
        ];
    }
}
