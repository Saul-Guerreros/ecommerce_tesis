<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrabajoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'trb_id'     => $this->trb_id,
            'trb_codigo' => $this->trb_codigo,
            'trb_descri' => $this->trb_descri,
            'trb_precio' => (float) $this->trb_precio,
            'trb_tivid'  => $this->trb_tivid,
            'trb_estado' => (int) $this->trb_estado,
            'trb_creado' => $this->trb_creado?->format('Y-m-d H:i'),
            'tipo_iva'   => $this->whenLoaded('tipoIva', fn () => [
                'tiv_id'     => $this->tipoIva->tiv_id,
                'tiv_descri' => $this->tipoIva->tiv_descri,
                'tiv_tasa'   => (float) $this->tipoIva->tiv_tasa,
            ]),
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}
