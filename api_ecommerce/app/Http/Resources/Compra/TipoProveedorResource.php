<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoProveedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpr_id'     => $this->tpr_id,
            'tpr_descri' => $this->tpr_descri,
            'tpr_estado' => $this->tpr_estado,
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}