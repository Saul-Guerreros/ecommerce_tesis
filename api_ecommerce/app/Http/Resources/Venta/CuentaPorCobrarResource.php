<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una factura a credito en la cartera. Espejo de CuentaPorPagarResource.
 */
class CuentaPorCobrarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proxVencim = $this->cco_proxvencim;

        return [
            'ven_id'         => (int) $this->ven_id,
            'ven_cliid'      => (int) $this->ven_cliid,
            'ven_nrodoc'     => $this->ven_nrodoc,
            'ven_cli_razon'  => $this->ven_cli_razon,
            'ven_cli_doc'    => $this->ven_cli_doc,
            'ven_fchaem'     => $this->ven_fchaem,
            'ven_total'      => round((float) $this->ven_total, 2),

            'cuo_total'      => (int) $this->cco_total,
            'cuo_pendientes' => (int) $this->cco_pendientes,
            'cuo_saldo'      => round((float) $this->cco_saldo, 2),
            'cuo_proxvencim' => $proxVencim,

            // Hay al menos una cuota pendiente con vencimiento pasado.
            'vencida' => $proxVencim !== null
                && $proxVencim < now()->toDateString(),
        ];
    }
}