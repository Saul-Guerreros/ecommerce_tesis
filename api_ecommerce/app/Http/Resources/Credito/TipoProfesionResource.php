<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoProfesionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpr_id'     => $this->tpr_id,
            'tpr_descri' => $this->tpr_descri,
        ];
    }
}