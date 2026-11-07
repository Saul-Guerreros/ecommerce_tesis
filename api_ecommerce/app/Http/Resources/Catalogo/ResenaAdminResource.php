<?php

namespace App\Http\Resources\Catalogo;

use App\Models\Catalogo\Resena;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requiere eager loading de 'producto.publicacionWeb', 'cliente.persona',
 * 'pedido' y 'usuarioRespuesta.persona'.
 */
class ResenaAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = $this->cliente?->persona;

        return [
            'res_id'     => (int) $this->res_id,
            'producto'   => $this->producto?->publicacionWeb?->pub_titulo ?? $this->producto?->prd_descri,
            'slug'       => $this->producto?->publicacionWeb?->pub_slug,
            'cliente'    => $persona
                ? trim(implode(' ', array_filter([
                    $persona->per_nombr1, $persona->per_nombr2, $persona->per_apell1, $persona->per_apell2,
                ])))
                : null,
            'cedula'     => $persona?->per_cedula,
            'pedido'     => $this->pedido?->ped_numero,
            'res_califi' => (int) $this->res_califi,
            'res_titulo' => $this->res_titulo,
            'res_coment' => $this->res_coment,
            'res_editad' => (bool) $this->res_editad,
            'res_estado' => (int) $this->res_estado,
            'visible'    => (int) $this->res_estado === Resena::VISIBLE,
            'res_motocu' => $this->res_motocu,
            'res_respue' => $this->res_respue,
            'res_fecres' => $this->res_fecres?->toIso8601String(),
            'respondido_por' => $this->usuarioRespuesta?->persona?->per_nombr1,
            'res_creado' => $this->res_creado?->toIso8601String(),
        ];
    }
}