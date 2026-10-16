<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalogo plano. NO lleva pro_tprid: profesion y tipo_profesion son
 * listas independientes, y el frontend muestra las dos completas sin
 * que ninguna filtre a la otra.
 */
class ProfesionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pro_id'     => $this->pro_id,
            'pro_descri' => $this->pro_descri,
        ];
    }
}