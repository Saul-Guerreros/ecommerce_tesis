<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mst_id'     => $this->mst_id,
            'mst_fecha'  => $this->mst_fecha,
            'mst_tipo'   => $this->mst_tipo,      // 'E' o 'S'
            'mst_cantid' => $this->mst_cantid,
            'mst_cosuni' => $this->mst_cosuni,
            'mst_salcan' => $this->mst_salcan,
            'mst_salcos' => $this->mst_salcos,
            'mst_obs'    => $this->mst_obs,

            // Origen del movimiento: compra, ajuste, o reversa de alguno de los dos.
            'mst_cmdid' => $this->mst_cmdid,
            'mst_aidid' => $this->mst_aidid,
            'mst_revid' => $this->mst_revid,
            'origen'    => $this->mst_cmdid ? 'Compra' : ($this->mst_aidid ? 'Ajuste' : '—'),

            'prd_codinv' => $this->whenLoaded('producto', fn () => $this->producto->prd_codinv),
            'prd_descri' => $this->whenLoaded('producto', fn () => $this->producto->prd_descri),
            'dpo_nombre' => $this->whenLoaded('deposito', fn () => $this->deposito->dpo_nombre),
        ];
    }
}