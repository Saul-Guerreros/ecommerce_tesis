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
            'prd_titulo' => $this->prd_titulo,
            'prd_codinv' => $this->prd_codinv,
            'prd_descri' => $this->prd_descri,
            'prd_precio' => (float) $this->prd_precio,
            'prd_stock'  => $this->prd_stock,
            'prd_stkmin' => $this->prd_stkmin,
            'prd_estado' => $this->prd_estado,
            'prd_creado' => $this->prd_creado,

            'prd_marid' => $this->prd_marid,
            'prd_catid' => $this->prd_catid,

            'marca' => $this->whenLoaded('marca', fn () => [
                'mar_id'     => $this->marca->mar_id,
                'mar_descri' => $this->marca->mar_descri,
            ]),

            'categoria' => $this->whenLoaded('categoria', fn () => [
                'cat_id'     => $this->categoria->cat_id,
                'cat_nombre' => $this->categoria->cat_nombre,
            ]),

            'imagenes' => $this->whenLoaded('imagenes', fn () => $this->imagenes->map(fn ($img) => [
                'img_id'     => $img->img_id,
                'img_url'    => $img->img_url,
                'img_orden'  => $img->img_orden,
                'img_princi' => (bool) $img->img_princi,
            ])),

            // stock bajo el mínimo -> útil para alertas en el dashboard
            'stock_bajo' => $this->prd_stock !== null && $this->prd_stock <= $this->prd_stkmin,
        ];
    }
}
