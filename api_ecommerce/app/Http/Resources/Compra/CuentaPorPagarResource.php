<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuentaPorPagarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'com_id'         => $this->com_id,
            'com_proid'      => $this->com_proid,
            'com_nrodoc'     => $this->com_nrodoc,
            'com_proraz'     => $this->com_proraz,
            'com_fchaem'     => $this->com_fchaem?->format('Y-m-d'),
            'com_total'      => $this->com_total,
            'cuo_total'      => (int) $this->cuo_total,
            'cuo_pendientes' => (int) $this->cuo_pendientes,
            'cuo_saldo'      => round((float) $this->cuo_saldo, 2),
            'cuo_provencim'  => $this->cuo_provencim,
        ];
    }
}