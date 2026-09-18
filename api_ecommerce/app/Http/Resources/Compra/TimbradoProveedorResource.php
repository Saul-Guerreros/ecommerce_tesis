<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimbradoProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpv_id'        => $this->tpv_id,
            'tpv_proid'     => $this->tpv_proid,
            'tpv_tcoid'     => $this->tpv_tcoid,
            'tpv_numero'    => $this->tpv_numero,
            'tpv_fechin'    => $this->tpv_fechin?->format('Y-m-d'),
            'tpv_fechfn'    => $this->tpv_fechfn?->format('Y-m-d'),
            'tpv_estado'    => $this->tpv_estado,
            'tco_descri'    => $this->whenLoaded('tipoComprobante', fn () => $this->tipoComprobante->tco_descri),
            'compras_count' => $this->whenCounted('compras'),
        ];
    }
}