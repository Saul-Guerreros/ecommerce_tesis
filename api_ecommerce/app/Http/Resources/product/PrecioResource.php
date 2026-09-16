<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrecioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'         => $this->prd_id,
            'prd_codinv'     => $this->prd_codinv,
            'prd_descri'     => $this->prd_descri,
            'tiv_tasa'       => $this->tiv_tasa,
            'costo_promedio' => $this->costo_promedio,   // null = todavía no tiene stock/compra

            'minorista' => [
                'margen' => $this->margen_min_efectivo,   // el que realmente se aplica
                'origen' => $this->prd_marmin !== null ? 'individual' : 'general',
                'precio' => $this->prd_pvtmin,            // null = todavía no se pudo calcular
            ],
            'mayorista' => [
                'margen' => $this->margen_maj_efectivo,
                'origen' => $this->prd_marmaj !== null ? 'individual' : 'general',
                'precio' => $this->prd_pvtmay,
            ],
            'umbral_mayorista' => [
                'cantidad' => $this->umbral_efectivo,
                'origen'   => $this->prd_canminmaj !== null ? 'individual' : 'general',
            ],
        ];
    }
}