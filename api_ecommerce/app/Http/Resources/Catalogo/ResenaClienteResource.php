<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;

/**
 * La reseña propia del cliente: lo público + su estado de moderación,
 * para que sepa si el administrador la ocultó y por qué.
 */
class ResenaClienteResource extends ResenaPublicaResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'visible'       => $this->esVisible(),
            'motivo_oculta' => $this->esVisible() ? null : $this->res_motocu,
        ]);
    }
}