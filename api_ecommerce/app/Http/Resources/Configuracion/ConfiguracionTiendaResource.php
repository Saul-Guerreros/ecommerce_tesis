<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionTiendaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ctn_id'            => $this->ctn_id,
            'ctn_pocstk_activo' => $this->ctn_pocstk_activo,
            'ctn_pocstk_umbral' => $this->ctn_pocstk_umbral,
        ];
    }
}