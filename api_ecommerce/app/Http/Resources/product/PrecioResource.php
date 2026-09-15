<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrecioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'          => $this->prd_id,
            'prd_codinv'      => $this->prd_codinv,
            'prd_descri'      => $this->prd_descri,
            'tiv_tasa'        => $this->tiv_tasa,          // NUEVO
            'costo_promedio'  => $this->costo_promedio,   // null = todavia no tiene stock/compra
            'prd_margen'      => $this->prd_margen,        // null = todavia no se definio
            'prd_prevta'      => $this->prd_prevta,        // null = todavia no se pudo calcular
        ];
    }
}