<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * precio_financiado y monto_cuota NO se leen de la tabla: se calculan al
 * momento sobre el precio de vitrina. Requiere eager loading de 'producto'.
 */
class ConfiguracionCuotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cfc_id'          => $this->cfc_id,
            'cfc_prdid'       => $this->cfc_prdid,
            'cfc_cantidad'    => (int) $this->cfc_cantidad,
            'cfc_recargo_pct' => (float) $this->cfc_recargo_pct,
            'cfc_estado'      => (bool) $this->cfc_estado,

            'precio_contado' => $this->whenLoaded(
                'producto',
                fn () => (float) ($this->producto->precioVitrina() ?? 0)
            ),
            'precio_financiado' => $this->whenLoaded(
                'producto',
                fn () => $this->precioFinanciado((float) ($this->producto->precioVitrina() ?? 0))
            ),
            'monto_cuota' => $this->whenLoaded(
                'producto',
                fn () => $this->montoCuota((float) ($this->producto->precioVitrina() ?? 0))
            ),
        ];
    }
}