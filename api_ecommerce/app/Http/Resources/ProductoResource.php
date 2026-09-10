<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'     => $this->prd_id,
            'prd_codinv' => $this->prd_codinv,
            'prd_descri' => $this->prd_descri,
            'prd_precio' => (float) $this->prd_precio,
            'prd_stock'  => $this->prd_stock,
            'prd_stkmin' => $this->prd_stkmin,
            'prd_estado' => $this->prd_estado,
            'prd_afestk' => (bool) $this->prd_afestk,
            'prd_creado' => $this->prd_creado,

            'prd_marid' => $this->prd_marid,
            'prd_tivid' => $this->prd_tivid,
            'prd_umdid' => $this->prd_umdid,

            'marca' => $this->whenLoaded('marca', fn () => [
                'mar_id'     => $this->marca->mar_id,
                'mar_descri' => $this->marca->mar_descri,
            ]),

            'tipo_iva' => $this->whenLoaded('tipoIva', fn () => [
                'tiv_id'     => $this->tipoIva->tiv_id,
                'tiv_descri' => $this->tipoIva->tiv_descri,
                'tiv_tasa'   => (float) $this->tipoIva->tiv_tasa,
            ]),

            'unidad_medida' => $this->whenLoaded('unidadMedida', fn () => [
                'umd_id'     => $this->unidadMedida->umd_id,
                'umd_codigo' => $this->unidadMedida->umd_codigo,
                'umd_nombre' => $this->unidadMedida->umd_nombre,
            ]),

            // Resumen de la ficha web: alcanza para pintar el listado
            // (título, imagen, categoría, si está publicado o no) sin
            // tener que pedir el detalle completo de la publicación.
            'publicacion_web' => $this->whenLoaded('publicacionWeb', function () {
                if (! $this->publicacionWeb) {
                    return null;
                }

                $imagenPrincipal = $this->publicacionWeb->imagenes
                    ->firstWhere('img_princi', 1) ?? $this->publicacionWeb->imagenes->first();

                return [
                    'pub_id'     => $this->publicacionWeb->pub_id,
                    'pub_titulo' => $this->publicacionWeb->pub_titulo,
                    'pub_prevta' => $this->publicacionWeb->pub_prevta !== null
                        ? (float) $this->publicacionWeb->pub_prevta
                        : null,
                    'pub_estado' => $this->publicacionWeb->pub_estado,
                    'categoria'  => $this->publicacionWeb->categoria?->cat_nombre,
                    'imagen_principal' => $imagenPrincipal?->img_url,
                ];
            }),

            // stock bajo el mínimo -> útil para alertas en el dashboard
            'stock_bajo' => $this->prd_stock !== null && $this->prd_stock <= $this->prd_stkmin,

            'prd_padre_id' => $this->prd_padre_id,

            'padre' => $this->whenLoaded('padre', fn () => $this->padre ? [
            'prd_id' => $this->padre->prd_id,
            'prd_codinv' => $this->padre->prd_codinv,
            'titulo' => $this->padre->publicacionWeb?->pub_titulo,
            ] : null),

            'variantes' => $this->whenLoaded('variantes', fn () => $this->variantes->map(fn ($v) => [
            'prd_id' => $v->prd_id,
            'prd_codinv' => $v->prd_codinv,
            'titulo' => $v->publicacionWeb?->pub_titulo,
            'imagen' => $v->publicacionWeb?->imagenes->firstWhere('img_princi', 1)?->img_url,
            ])),
        ];
    }
}
