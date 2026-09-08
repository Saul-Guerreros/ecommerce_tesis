<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarrioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bar_id'     => $this->bar_id,
            'bar_ciuid'  => $this->bar_ciuid,
            'bar_descri' => $this->bar_descri,
            'bar_estado' => $this->bar_estado,
            'bar_creado' => $this->bar_creado,

            'ciu_descri' => $this->whenLoaded('ciudad', fn () => $this->ciudad->ciu_descri),
            // El departamento llega por la cadena ciudad->departamento: el
            // listado lo muestra para desambiguar barrios homonimos.
            'dep_descri' => $this->whenLoaded('ciudad', fn () => $this->ciudad->departamento?->dep_descri),

            'usos_count'   => $this->whenNotNull($this->usos_count),
            'usos_detalle' => $this->whenNotNull($this->usos_detalle),
        ];
    }
}
