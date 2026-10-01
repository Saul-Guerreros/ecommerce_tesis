<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarritoResource extends JsonResource{
    /**
     * Requiere que el controlador haga eager loading de:
     * 'producto.publicacionWeb.imagenes'
     * (evita N+1 al recorrer el listado del carrito).
     */
    public function toArray(Request $request): array{
        return[
            'car_id'=> $this->car_id,
            'car_idpro'=> $this->car_idpro,

            'car_tipdes'=> $this->car_tipdes,
            'car_descue'=> $this->car_descue,
            'car_tipcam'=> $this->car_tipcam,
            'car_codcup'=> $this->car_codcup,
            'car_coddes'=> $this->car_coddes,

            'car_cantid'=> $this->car_cantid,
            'car_precio'=> $this->car_precio,
            'car_subtot'=> $this->car_subtot,
            'car_total'=> $this->car_total,
            'car_creado'=> $this->car_creado,
            'car_modpag'     => $this->car_modpag,
            'car_numcuo'     => $this->car_numcuo,
            'car_recargopct' => $this->car_recargopct,
            'car_montocuo'   => $this->car_montocuo,

            
            // ----- Inventario (el frontend lo usa para topear el [+]) -----
            // La regla real sigue viviendo en el backend (CarritoController
            // valida y responde 422); esto es solo para no dejar que el
            // usuario llegue a pedir una cantidad imposible.
            'stock_disponible'=> $this->whenLoaded('producto', function () {
                return $this->producto->stockDisponible();
            }),

            'controla_stock'=> $this->whenLoaded('producto', fn () => (bool) $this->producto->prd_afestk),

            // ----- Minorista/mayorista (punto 7-8 de "Carrito Completo") -----
            // Mismos nombres de campo que ProductoCuotaResource y PublicacionWebResource:
            // le permite al frontend bloquear el '-' del selector de cantidad ANTES
            // de golpear el backend, en vez de esperar el 422 de precioUnitarioOFallar().
            'cantidad_minima'=> $this->whenLoaded('producto', fn () => (float) $this->producto->cantidadMinimaVenta()),
            'solo_mayorista'=> $this->whenLoaded('producto', fn () => (bool) $this->producto->soloMayorista()),

            // ----- IVA (punto 16) -----
            // car_total YA incluye el IVA (viene de prd_pvtmin/prd_pvtmay,
            // que PriceService calcula "con IVA" adentro). car_iva es el
            // desglose informativo de esa misma cifra, no un monto a sumar.
            'car_iva'=> $this->whenLoaded('producto', fn () => $this->producto->ivaIncluidoEn((float) $this->car_total)),

            
            // ----- Producto (lo necesario para pintar la fila) -----
            'prd_descri'=> $this->whenLoaded('producto', fn () => $this->producto->prd_descri),

            'publicacion_web'=> $this->whenLoaded('producto', function () {
                $publicacion= $this->producto->publicacionWeb;

                if (! $publicacion){
                    return null;
                }

                $imagenPrincipal= $publicacion->imagenes
                    ->firstWhere('img_princi', 1)
                    ?? $publicacion->imagenes->first();

                return [
                    'pub_titulo'=> $publicacion->pub_titulo,
                    'pub_slug'=> $publicacion->pub_slug,
                    'imagen'=> $this->urlImagen($imagenPrincipal?->img_url),
                    'categoria'=> $publicacion->categoria?->cat_nombre,
                ];
            }),

        ];
    }

       /**
     * Convierte lo que haya en imagen_producto.img_url en una URL absoluta.
     *
     * El campo no es homogéneo: según por dónde se haya cargado la imagen
     * puede venir como ruta de disco ("productos/abc.jpg") o ya con el
     * prefijo web ("/storage/productos/abc.jpg"). Se normaliza a ruta de
     * disco antes de pasarla por asset(), para no terminar con
     * "/storage/storage/..." en el segundo caso.
     */
    private function urlImagen(?string $ruta): ?string{
        if (! $ruta) {
            return null;
        }

        if (str_starts_with($ruta, 'http')) {
            return $ruta;
        }

        $relativa = ltrim($ruta, '/');

        if (str_starts_with($relativa, 'storage/')) {
            $relativa = substr($relativa, strlen('storage/'));
        }

        return asset('storage/'.$relativa);
    }
    

}