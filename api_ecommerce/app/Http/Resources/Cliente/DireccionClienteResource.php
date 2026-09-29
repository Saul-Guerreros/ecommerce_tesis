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
            'dir_barid'      => $this->dir_barid,
            'barrio'         => $this->barrio?->bar_descri,
            'ciudad'         => $this->barrio?->ciudad?->ciu_descri,
            'dir_calid'      => $this->dir_calid,
            'calle'          => $this->calle?->cal_descri,
            'dir_nrocasa'    => $this->dir_nrocasa,
            'dir_referencia' => $this->dir_referencia,
            'dir_celular'    => $this->dir_celular,
            'dir_telef'      => $this->dir_telef,
            'dir_predet'     => (bool) $this->dir_predet,
        ];
    }
}