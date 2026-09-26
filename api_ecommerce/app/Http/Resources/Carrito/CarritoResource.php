<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarritoResource extends JsonResource{
    /**
     * Requiere que el controlador haga eager loading de:
     * 'producto.publicacionWeb.imagenes', 'variacion.propiedades.atributo'
     * (evita N+1 al recorrer el listado del carrito).
     */
    public function toArray(Request $request): array{
        return[
            'car_id'=> $this->car_id,
            'car_idpro'=> $this->car_idpro,
            'car_idvarp'=> $this->car_idvarp,

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
                ];
            }),

            // ----- Variación seleccionada (ej: Color: Rojo, Talla: M) -----
            'variacion'=> $this->whenLoaded('variacion', function () {
                if (! $this->variacion) {
                    return null;
                }

                return[
                    'var_proid'=> $this->variacion->var_proid,
                    'var_preadi'=> $this->variacion->var_preadi,
                    'propiedades'=> $this->variacion->propiedades->map(fn ($propiedad)=>[
                        'atb_nombre'=> $propiedad->atributo->atb_nombre,
                        'prp_nombre'=> $propiedad->prp_nombre,
                    ]),
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