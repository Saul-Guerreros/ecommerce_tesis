<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudAdjuntoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sad_id'     => $this->sad_id,
            'sad_tipo'   => $this->sad_tipo,
            'sad_nombre' => $this->sad_nombre,
            'url'        => $this->url,
            'sad_subido' => $this->sad_subido?->format('Y-m-d H:i'),
        ];
    }
}
