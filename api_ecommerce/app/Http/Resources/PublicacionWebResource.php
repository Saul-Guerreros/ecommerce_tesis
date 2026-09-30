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
            'pub_slug'   => $this->pub_slug,
            'pub_descwb' => $this->pub_descwb,
            'pub_meta_titulo'      => $this->pub_meta_titulo,
            'pub_meta_descripcion' => $this->pub_meta_descripcion,

            'pub_estado' => $this->pub_estado,
            // null = usa el umbral general de Configuración de la Tienda.
            'pub_pocstk_umbral' => $this->pub_pocstk_umbral,

            'pub_creado' => $this->pub_creado,

            'pub_actualizado' => $this->pub_actualizado,

            'producto' => $this->whenLoaded('producto', fn () => [
                'prd_id'     => $this->producto->prd_id,
                'prd_codinv' => $this->producto->prd_codinv,
                'prd_descri' => $this->producto->prd_descri,
                'prd_estado' => $this->producto->prd_estado,
            ]),

            /**
             * Precios de venta, tal como los gestiona Gestión de Precios.
             * Son SOLO LECTURA: publicacion_web no los guarda ni los puede
             * editar. El formulario de Publicación Web los muestra para que
             * el admin sepa a cuánto va a salir el producto en la tienda,
             * pero el dueño del dato es `producto`.
             *
             * Requiere la relación 'producto' cargada.
             */
            'precios' => $this->whenLoaded('producto', fn () => [
                'minorista' => [
                    'habilitado' => (bool) $this->producto->prd_habmin,
                    'precio'     => $this->producto->prd_pvtmin !== null ? (float) $this->producto->prd_pvtmin : null,
                ],
                'mayorista' => [
                    'habilitado'      => (bool) $this->producto->prd_habmaj,
                    'precio'          => $this->producto->prd_pvtmay !== null ? (float) $this->producto->prd_pvtmay : null,
                    'cantidad_minima' => $this->producto->prd_habmaj ? $this->producto->umbralMayorista() : null,
                ],
                // Se vende solo al por mayor: en la tienda se publica con la
                // leyenda "desde X unidades" y no se puede comprar suelto.
                'solo_mayorista' => $this->producto->soloMayorista(),
                // Precio que verá el cliente en la vitrina.
                'precio_vitrina' => $this->producto->precioVitrina(),
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