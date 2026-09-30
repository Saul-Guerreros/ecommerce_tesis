<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pgo_id'         => $this->pgo_id,
            'pgo_cuoid'      => $this->pgo_cuoid,
            'pgo_fecha'      => $this->pgo_fecha?->format('Y-m-d'),
            'pgo_formapa'    => $this->pgo_formapa,
            'pgo_referencia' => $this->pgo_referencia,
            'pgo_observ'     => $this->pgo_observ,
            'pgo_importe'    => $this->pgo_importe,
            'pgo_estado'     => $this->pgo_estado,
            'cuo_nrocuo'     => $this->whenLoaded('cuota', fn () => $this->cuota->cuo_nrocuo),
            'com_nrodoc'     => $this->whenLoaded('cuota', fn () => $this->cuota->compraCab?->com_nrodoc),
            'com_proraz'     => $this->whenLoaded('cuota', fn () => $this->cuota->compraCab?->com_proraz),
            'pgo_motanu' => $this->pgo_motanu,
        ];
    }
}