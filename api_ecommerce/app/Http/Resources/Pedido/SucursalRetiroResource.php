<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SucursalRetiroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'suc_id'        => $this->suc_id,
            'suc_nombre'    => $this->suc_nombre,
            'suc_direccion' => $this->suc_direccion,
            'suc_horario'   => $this->suc_horario,
            'suc_telef'     => $this->suc_telef ?? $this->suc_cel,
            'ciudad'        => $this->whenLoaded('ciudad', fn () => $this->ciudad->ciu_descri),
        ];
    }
}