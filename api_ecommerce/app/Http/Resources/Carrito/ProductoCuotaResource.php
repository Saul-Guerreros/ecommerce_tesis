<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del listado de "Gestión de Cuotas". Requiere withCount de
 * cuotas_total y cuotas_activas en el controlador.
 */
class ProductoCuotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'     => $this->prd_id,
            'prd_codinv' => $this->prd_codinv,
            'prd_descri' => $this->prd_descri,

            'precio_contado' => $this->precioVitrina() !== null
                ? (float) $this->precioVitrina()
                : null,

            'solo_mayorista'  => $this->soloMayorista(),
            'cantidad_minima' => (float) $this->cantidadMinimaVenta(),

            'cuotas_total'   => (int) ($this->cuotas_total ?? 0),
            'cuotas_activas' => (int) ($this->cuotas_activas ?? 0),
        ];
    }
}