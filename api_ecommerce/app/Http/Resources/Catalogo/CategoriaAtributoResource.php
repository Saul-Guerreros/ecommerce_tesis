<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaAtributoResource extends JsonResource
{
    /** atb_tipo: 1 = Texto | 2 = Número | 3 = Seleccionable | 4 = Selección múltiple */
    private const TIPOS = [
        1 => 'Texto',
        2 => 'Número',
        3 => 'Seleccionable',
        4 => 'Selección múltiple',
    ];

    public function toArray(Request $request): array
    {
        $atributo = $this->whenLoaded('atributo');

        return [
            'cta_id'     => $this->cta_id,
            'cta_catid'  => $this->cta_catid,
            'cta_atbid'  => $this->cta_atbid,
            'cta_orden'  => $this->cta_orden,
            'cta_obliga' => $this->cta_obliga,
            'cta_creado' => $this->cta_creado,

            'atb_nombre'     => $this->whenLoaded('atributo', fn () => $atributo->atb_nombre),
            'atb_tipo'       => $this->whenLoaded('atributo', fn () => $atributo->atb_tipo),
            'atb_unidad'     => $this->whenLoaded('atributo', fn () => $atributo->atb_unidad),
            'atb_tipo_descri' => $this->whenLoaded('atributo', fn () => self::TIPOS[$atributo->atb_tipo] ?? '—'),

            // Un atributo seleccionable sin propiedades no se puede completar
            // en la ficha: el front lo marca como advertencia.
            'propiedades_count' => $this->whenLoaded('atributo', fn () => $atributo->propiedades_count),
            'sin_opciones'      => $this->whenLoaded('atributo', fn () =>
                in_array($atributo->atb_tipo, [3, 4]) && (int) $atributo->propiedades_count === 0
            ),
        ];
    }
}
