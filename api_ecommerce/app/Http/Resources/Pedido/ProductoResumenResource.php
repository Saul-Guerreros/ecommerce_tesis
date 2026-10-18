<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de la tarjeta "Productos en Carrito" cuando el pedido ya existe
 * (Paso 3 del checkout y Mis Pedidos). Es la contraparte, sobre
 * DetallePedido, de lo que CarritoResource entrega sobre Carrito para los
 * Pasos 1 y 2 — misma idea de tarjeta, dos fuentes de datos distintas
 * porque en un punto el carrito deja de existir y el pedido ya está
 * confirmado.
 *
 * 'nombre' sale de dpd_descri (snapshot tomado al confirmar el pedido), no
 * de producto.prd_descri: si el producto se renombra en el catálogo
 * después, esta tarjeta tiene que seguir mostrando el nombre con el que el
 * cliente compró.
 *
 * Requiere eager loading de 'producto.publicacionWeb.imagenes'.
 */
class ProductoResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publicacion = $this->producto?->publicacionWeb;

        $imagenPrincipal = $publicacion?->imagenes
            ->firstWhere('img_princi', 1)
            ?? $publicacion?->imagenes->first();

        return [
            'imagen'   => $this->urlImagen($imagenPrincipal?->img_url),
            'nombre'   => $this->dpd_descri,
            'cantidad' => (float) $this->dpd_cantid,
            'total'    => (float) $this->dpd_total,
        ];
    }

    /**
     * Misma normalización que CarritoResource::urlImagen(): el campo
     * img_url no es homogéneo (puede venir con o sin el prefijo /storage).
     */
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