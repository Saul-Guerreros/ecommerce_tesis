<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionEnvioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cfe_id'           => $this->cfe_id,
            'cfe_modo'         => $this->cfe_modo,
            'cfe_costo_fijo'   => $this->cfe_costo_fijo !== null ? (float) $this->cfe_costo_fijo : null,
            'cfe_monto_minimo' => $this->cfe_monto_minimo !== null ? (float) $this->cfe_monto_minimo : null,
        ];
    }
}