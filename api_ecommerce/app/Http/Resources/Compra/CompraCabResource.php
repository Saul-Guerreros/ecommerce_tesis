<?php
// app/Http/Resources/Compra/CompraCabResource.php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraCabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'com_id'     => $this->com_id,
            'com_proid'  => $this->com_proid,
            'com_depid'  => $this->com_depid,
            'com_sucid'  => $this->com_sucid,
            'com_tcoid'  => $this->com_tcoid,
            'com_tpvid'  => $this->com_tpvid,
            'com_proruc' => $this->com_proruc,
            'com_proraz' => $this->com_proraz,
            'com_timbra' => $this->com_timbra,
            'com_timbvn' => $this->com_timbvn?->format('Y-m-d'),
            'com_nrodoc' => $this->com_nrodoc,
            'com_fchaem' => $this->com_fchaem?->format('Y-m-d'),
            'com_condic' => $this->com_condic,
            'com_plzdia' => $this->com_plzdia,
            'com_cuotas' => $this->com_cuotas,
            'com_interv' => $this->com_interv,
            'com_fchvto' => $this->com_fchvto?->format('Y-m-d'),
            'com_exenta' => $this->com_exenta,
            'com_grav5'  => $this->com_grav5,
            'com_grav10' => $this->com_grav10,
            'com_iva5'   => $this->com_iva5,
            'com_iva10'  => $this->com_iva10,
            'com_total'  => $this->com_total,
            'com_estfac' => $this->com_estfac,
            'com_obs'    => $this->com_obs,
            'com_creado' => $this->com_creado,
            'com_fecanu' => $this->com_fecanu,

            'suc_nombre'      => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
            'dpo_nombre'      => $this->whenLoaded('deposito', fn () => $this->deposito->dpo_nombre),
            'tco_descri'      => $this->whenLoaded('tipoComprobante', fn () => $this->tipoComprobante->tco_descri),

            'detalle' => CompraDetalleResource::collection($this->whenLoaded('detalle')),
        ];
    }
}