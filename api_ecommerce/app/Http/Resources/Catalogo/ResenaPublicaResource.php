<?php

namespace App\Http\Resources\Catalogo;

use App\Services\Catalogo\ResenaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reseña tal como se ve en la ficha del producto. No expone ids de
 * cliente, pedido ni el nombre completo de la persona.
 *
 * Requiere eager loading de 'cliente.persona' y 'producto.publicacionWeb'.
 */
class ResenaPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => (int) $this->res_id,
            'autor'             => ResenaService::nombrePublico($this->cliente?->persona),
            'calificacion'      => (int) $this->res_califi,
            'titulo'            => $this->res_titulo,
            'comentario'        => $this->res_coment,
            // Variante que compró (ej. "Notebook X - Gris"): útil porque las
            // reseñas se comparten entre todos los colores.
            'producto_comprado' => $this->producto?->publicacionWeb?->pub_titulo,
            'editada'           => (bool) $this->res_editad,
            'fecha'             => $this->res_creado?->toIso8601String(),
            'respuesta'         => $this->res_respue ? [
                'texto' => $this->res_respue,
                'fecha' => $this->res_fecres?->toIso8601String(),
            ] : null,
        ];
    }
}