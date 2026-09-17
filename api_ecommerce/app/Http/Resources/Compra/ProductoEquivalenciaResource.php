<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoEquivalenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'peq_id'     => $this->peq_id,
            'peq_prdid'  => $this->peq_prdid,
            'peq_umdid'  => $this->peq_umdid,
            'peq_factor' => $this->peq_factor,
            'peq_estado' => $this->peq_estado,
            'umd_codigo' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_codigo),
            'umd_nombre' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_nombre),
        ];
    }
}