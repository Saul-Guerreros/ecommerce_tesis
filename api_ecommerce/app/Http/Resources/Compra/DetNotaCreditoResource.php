<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetNotaCreditoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dnc_id'           => $this->dnc_id,
            'dnc_cmdid'        => $this->dnc_cmdid,
            'dnc_cantidad'     => $this->dnc_cantidad,
            'dnc_base_gravada' => $this->dnc_base_gravada,
            'dnc_iva'          => $this->dnc_iva,
            'dnc_total'        => $this->dnc_total,
            'dnc_canaju'       => $this->dnc_canaju,
            'cmd_descri'       => $this->whenLoaded('compraDetalle', fn () => $this->compraDetalle->cmd_descri),
            'prd_codinv'       => $this->whenLoaded('compraDetalle', fn () => $this->compraDetalle->producto->prd_codinv ?? null),
        ];
    }
}