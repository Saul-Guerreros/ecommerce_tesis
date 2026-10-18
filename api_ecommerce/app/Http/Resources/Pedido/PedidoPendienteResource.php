<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila del aviso de "Pedidos pendientes de pago": el punto rojo sobre
 * el ícono de perfil, la tarjeta flotante de la Home y el contador del
 * ítem "Mis pedidos" del dropdown se alimentan todos de esta misma lista.
 *
 * 'producto' muestra sólo el primer ítem del pedido — no todos — porque
 * el aviso tiene que caber en una fila. 'productos_adicionales' es la
 * forma de no mentir por omisión cuando el pedido tiene más de un
 * producto: el frontend lo muestra como "+N" junto al nombre.
 *
 * Requiere eager loading de 'detalles.producto.publicacionWeb.imagenes'.
 */
class PedidoPendienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detalles = $this->detalles;
        $primero  = $detalles->first();

        $producto     = $primero?->producto;
        $publicacion  = $producto?->publicacionWeb;
        $imagen       = $publicacion?->imagenes->firstWhere('img_princi', 1)
            ?? $publicacion?->imagenes->first();

        return [
            'ped_numero' => $this->ped_numero,
            'ped_total'  => (float) $this->ped_total,
            'ped_vence'  => $this->ped_vence?->toIso8601String(),

            'producto' => $primero ? [
                'imagen'   => $this->urlImagen($imagen?->img_url),
                'nombre'   => $primero->dpd_descri,
                'cantidad' => (float) $primero->dpd_cantid,
            ] : null,

            'productos_adicionales' => max(0, $detalles->count() - 1),
        ];
    }

    /** Misma normalización que ProductoResumenResource::urlImagen(). */
    private function urlImagen(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }

        if (str_starts_with($ruta, 'http')) {
            return $ruta;
        }

        $relativa = ltrim($ruta, '/');

        if (str_starts_with($relativa, 'storage/')) {
            $relativa = substr($relativa, strlen('storage/'));
        }

        return asset('storage/' . $relativa);
    }
}