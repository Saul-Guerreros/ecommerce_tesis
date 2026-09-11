<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicacionWebResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pub_id'     => $this->pub_id,
            'pub_prdid'  => $this->pub_prdid,
            'pub_catid'  => $this->pub_catid,
            'pub_titulo' => $this->pub_titulo,
            'pub_prevta' => $this->pub_prevta !== null ? (float) $this->pub_prevta : null,
            'pub_slug'   => $this->pub_slug,
            'pub_descwb' => $this->pub_descwb,
            'pub_meta_titulo'      => $this->pub_meta_titulo,
            'pub_meta_descripcion' => $this->pub_meta_descripcion,
            'pub_estado' => $this->pub_estado,
           
            'pub_creado' => $this->pub_creado,
            'pub_actualizado' => $this->pub_actualizado,

            'producto' => $this->whenLoaded('producto', fn () => [
                'prd_id'     => $this->producto->prd_id,
                'prd_codinv' => $this->producto->prd_codinv,
                'prd_prevta' => $this->producto->prd_prevta !== null ? (float) $this->producto->prd_prevta : null,
                'prd_estado' => $this->producto->prd_estado,
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

            'especificaciones' => $this->whenLoaded('especificaciones', fn () => $this->especificaciones->map(fn ($esp) => [
                'esp_id'     => $esp->esp_id,
                'esp_atbid'  => $esp->esp_atbid,
                'esp_prpid'  => $esp->esp_prpid,
                'esp_valibr' => $esp->esp_valibr,
                'esp_valnum' => $esp->esp_valnum,
            ])),
			
			'vitrina' => $this->whenLoaded('vitrinas', function () {
    $porSeccion = $this->vitrinas->keyBy('vip_seccion');
 
    return [
        'featured' => $porSeccion->get('featured')?->vip_orden,
        'trending' => $porSeccion->get('trending')?->vip_orden,
        'selling'  => $porSeccion->get('selling')?->vip_orden,
    ];
}),
        ];
    }
}