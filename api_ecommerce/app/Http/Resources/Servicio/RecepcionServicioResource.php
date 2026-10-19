<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecepcionServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rse_id'      => $this->rse_id,
            'rse_fecha'   => $this->rse_fecha?->format('Y-m-d H:i'),
            'rse_estfis'  => $this->rse_estfis,
            'rse_obs'     => $this->rse_obs,
            // La clave nunca viaja en el listado; solo se informa si existe.
            'tiene_clave' => !is_null($this->getRawOriginal('rse_clave')),
            'recibido_por' => $this->whenLoaded('recibidoPor', fn () => $this->recibidoPor?->persona?->nombre_completo),
            'tecnico'     => $this->whenLoaded('tecnico', fn () => [
                'usu_id' => $this->tecnico?->usu_id,
                'nombre' => $this->tecnico?->persona?->nombre_completo,
            ]),
            'accesorios'  => $this->whenLoaded('accesorios', fn () => $this->accesorios->map(fn ($a) => [
                'rac_id'     => $a->rac_id,
                'rac_descri' => $a->rac_descri,
                'rac_devuel' => $a->rac_devuel,
            ])),
        ];
    }
}
