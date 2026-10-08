<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TarifaEnvioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tev_id'     => $this->tev_id,
            'tev_sucid'  => $this->tev_sucid,
            'tev_ciuid'  => $this->tev_ciuid,
            'tev_costo'  => (float) $this->tev_costo,
            'sucursal'   => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
            'ciudad'     => $this->whenLoaded('ciudad', fn () => $this->ciudad->ciu_descri),
        ];
    }
}