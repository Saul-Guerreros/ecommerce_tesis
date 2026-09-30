<?php
namespace App\Http\Resources\Compra;

use App\Models\Compras\CuotaCompra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuotaCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cuo_id'      => $this->cuo_id,
            'cuo_comid'   => $this->cuo_comid,
            'cuo_nrocuo'  => $this->cuo_nrocuo,
            'cuo_venci'   => $this->cuo_venci?->format('Y-m-d'),
            'cuo_importe' => $this->cuo_importe,
            'cuo_estado'  => $this->cuo_estado,
            'cuo_fecpago' => $this->cuo_fecpago?->format('Y-m-d'),
            'com_nrodoc'  => $this->whenLoaded('compraCab', fn () => $this->compraCab->com_nrodoc),
            'com_proraz'  => $this->whenLoaded('compraCab', fn () => $this->compraCab->com_proraz),
            'com_proid'   => $this->whenLoaded('compraCab', fn () => $this->compraCab->com_proid),
            'cuo_saldo'   => $this->cuo_estado === CuotaCompra::ESTADO_PAGADA ? 0 : (float) $this->cuo_importe,
            'pago'        => $this->whenLoaded('pago', fn () => $this->pago ? new PagoCompraResource($this->pago) : null),
        ];
    }
}