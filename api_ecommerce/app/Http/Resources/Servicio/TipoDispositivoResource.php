<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoDispositivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tdi_id'     => $this->tdi_id,
            'tdi_codigo' => $this->tdi_codigo,
            'tdi_descri' => $this->tdi_descri,
            'tdi_estado' => (int) $this->tdi_estado,
            'tdi_creado' => $this->tdi_creado?->format('Y-m-d H:i'),
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}
