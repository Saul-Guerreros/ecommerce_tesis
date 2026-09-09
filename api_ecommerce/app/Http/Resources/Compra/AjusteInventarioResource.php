<?php
// app/Http/Resources/Compra/AjusteInventarioResource.php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AjusteInventarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'aji_id'         => $this->aji_id,
            'aji_tpaid'      => $this->aji_tpaid,
            'aji_depid'      => $this->aji_depid,
            'aji_fecha'      => $this->aji_fecha?->format('Y-m-d'),
            'aji_referencia' => $this->aji_referencia,
            'aji_obs'        => $this->aji_obs,
            'aji_estado'     => $this->aji_estado,
            'aji_creado'     => $this->aji_creado,
            'aji_confir'     => $this->aji_confir,
            'aji_anulado'    => $this->aji_anulado,

            'tpa_descri' => $this->whenLoaded('tipoAjuste', fn () => $this->tipoAjuste->tpa_descri),
            'tpa_natura' => $this->whenLoaded('tipoAjuste', fn () => $this->tipoAjuste->tpa_natura),
            'dpo_nombre' => $this->whenLoaded('deposito', fn () => $this->deposito->dpo_nombre),

            'detalle' => AjusteInventarioDetalleResource::collection($this->whenLoaded('detalle')),
        ];
    }
}