<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disponible = round(((float) $this->sto_cantid) - ((float) $this->sto_reserv), 4);

        return [
            'sto_id'      => $this->sto_id,
            'sto_cantid'  => $this->sto_cantid,
            'sto_reserv'  => $this->sto_reserv,
            'sto_cosprm'  => $this->sto_cosprm,
            'sto_actual'  => $this->sto_actual,
            'disponible'  => $disponible,
            'valorizado'  => round(((float) $this->sto_cantid) * ((float) $this->sto_cosprm), 2),

            'prd_codinv' => $this->whenLoaded('producto', fn () => $this->producto->prd_codinv),
            'prd_descri' => $this->whenLoaded('producto', fn () => $this->producto->prd_descri),
            'prd_stkmin' => $this->whenLoaded('producto', fn () => $this->producto->prd_stkmin),
            'dpo_nombre' => $this->whenLoaded('deposito', fn () => $this->deposito->dpo_nombre),

            // Util para pintar una alerta en el listado si esta por debajo del minimo.
            'bajo_minimo' => $this->whenLoaded('producto', fn () =>
                $this->producto->prd_stkmin !== null && (float) $this->sto_cantid < (float) $this->producto->prd_stkmin
            ),
        ];
    }
}