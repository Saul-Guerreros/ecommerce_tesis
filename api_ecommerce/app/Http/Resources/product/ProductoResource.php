<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'      => $this->prd_id,
            'prd_marid'   => $this->prd_marid,
            'prd_tivid'   => $this->prd_tivid,
            'prd_umdid'   => $this->prd_umdid,
            'prd_codinv'  => $this->prd_codinv,
            'prd_descri'  => $this->prd_descri,
            'prd_stkmin'  => $this->prd_stkmin,
            'prd_afestk'  => $this->prd_afestk,
            'prd_estado'  => $this->prd_estado,
            'prd_creado'  => $this->prd_creado,

            'mar_descri' => $this->whenLoaded('marca', fn () => $this->marca->mar_descri),
            'tiv_descri' => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_descri),
            'tiv_tasa'   => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_tasa),
            'umd_codigo' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_codigo),
            'umd_nombre' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_nombre),

            /**
             * stock_actual y precio_sugerido llegan como atributos dinamicos
             * calculados en el controlador (ver ProductoController::conStock()),
             * no como relaciones Eloquent directas: son agregados sobre
             * TODOS los depositos del producto, no un valor de una sola fila.
             *
             * Los campos de precio propiamente dichos (prd_pvtmin, prd_pvtmay,
             * prd_marmin, prd_marmaj, prd_canminmaj) NO se exponen acá: el
             * módulo de Producto ya no los muestra en ningún lado (ver
             * form-producto y list-producto). Viven exclusivamente en
             * Gestión de Precios (PrecioResource), que es quien los expone.
             */
            'stock_actual'    => $this->whenNotNull($this->stock_actual),
            'precio_sugerido' => $this->whenNotNull($this->precio_sugerido),

            /**
             * Resumen de la publicación web (null si el producto todavía no
             * fue publicado en la tienda). Ver PublicacionWebResumen en el
             * frontend (producto.model.ts) para el shape esperado.
             */
            'publicacion_web' => $this->whenLoaded('publicacionWeb', function () {
                if (! $this->publicacionWeb) {
                    return null;
                }

                $imagenPrincipal = $this->publicacionWeb->imagenes
                    ->firstWhere('img_princi', 1)
                    ?? $this->publicacionWeb->imagenes->first();

                return [
                    'pub_id'           => $this->publicacionWeb->pub_id,
                    'pub_titulo'       => $this->publicacionWeb->pub_titulo,
                    'pub_prevta'       => $this->publicacionWeb->pub_prevta,
                    'pub_estado'       => $this->publicacionWeb->pub_estado,
                    'categoria'        => $this->publicacionWeb->categoria?->cat_nombre,
                    'imagen_principal' => $imagenPrincipal?->img_url,
                ];
            }),
        ];
    }
}
