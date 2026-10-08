<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DireccionClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dir_id'         => $this->dir_id,
            'dir_alias'      => $this->dir_alias,
            'dir_ciuid'      => $this->dir_ciuid,
            'ciudad'         => $this->ciudad?->ciu_descri,
            'departamento'   => $this->ciudad?->departamento?->dep_descri,
            'dir_barrio'     => $this->dir_barrio,
            'dir_calle'      => $this->dir_calle,
            'dir_nrocasa'    => $this->dir_nrocasa,
            'dir_referencia' => $this->dir_referencia,
            'dir_celular'    => $this->dir_celular,
            'dir_telef'      => $this->dir_telef,
            'dir_predet'     => (bool) $this->dir_predet,
            'resumen'        => $this->resumen,
        ];
    }
}