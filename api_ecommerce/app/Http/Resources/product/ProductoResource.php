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
            // (float) quita los ceros de la cast decimal:4: "5.0000" -> 5, "5.2500" -> 5.25
            'prd_stkmin'  => $this->prd_stkmin !== null ? (float) $this->prd_stkmin : null,
            'prd_afestk'  => $this->prd_afestk,
            'prd_repues'  => (int) $this->prd_repues,
            'prd_estado'  => $this->prd_estado,
            'prd_creado'  => $this->prd_creado,
            'prd_padre_id' => $this->prd_padre_id,

            'mar_descri' => $this->whenLoaded('marca', fn () => $this->marca->mar_descri),
            'tiv_descri' => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_descri),
            'tiv_tasa'   => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_tasa),
            'umd_codigo' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_codigo),
            'umd_nombre' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_nombre),

            /**
             * Variación (Sistema B): modelo base (si ESTE producto es una
             * variante) y/o lista de variantes (si otros productos lo
             * tienen a él como modelo base). Un producto nunca tiene
             * ambos a la vez (ver regla en ProductoRequest).
             */
            'padre' => $this->whenLoaded('padre', function () {
                if (!$this->padre) {
                    return null;
                }
                return [
                    'prd_id'     => $this->padre->prd_id,
                    'prd_codinv' => $this->padre->prd_codinv,
                    'prd_descri' => $this->padre->prd_descri,
                ];
            }),
            'variantes' => $this->whenLoaded('variantes', fn () => $this->variantes->map(fn ($v) => [
                'prd_id'     => $v->prd_id,
                'prd_codinv' => $v->prd_codinv,
                'prd_descri' => $v->prd_descri,
                'prd_estado' => $v->prd_estado,
                'pub_estado' => $v->publicacionWeb?->pub_estado,
                'mar_descri' => $v->relationLoaded('marca') ? $v->marca?->mar_descri : null,
                'umd_codigo' => $v->relationLoaded('unidadMedida') ? $v->unidadMedida?->umd_codigo : null,
                'stock_actual' => $v->stock_actual ?? null,
                'usos_count' => $v->usos_count ?? null,
            ])),

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
             * Cuántas veces aparece en compra_detalle/stock (ver
             * ProductoController::contarUsos()) -- lo usa el frontend para
             * bloquear "Eliminar" con aviso, en vez de dejar que reviente
             * contra la restricción de FK. NOTA: esto NO se estaba
             * exponiendo acá (bug preexistente); DeleteProductoComponent
             * ya esperaba este campo, solo faltaba este renglón.
             */
            'usos_count' => $this->whenNotNull($this->usos_count),

            // Igual que stock_actual: viene de withCount('variantes') en
            // index(), no de la relación cargada entera (el listado
            // colapsado solo necesita saber SI tiene variantes, para
            // mostrar la flecha de expandir -- la lista real se pide
            // aparte, al expandir, vía GET /productos/{id}).
            'variantes_count' => $this->whenNotNull($this->variantes_count),

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