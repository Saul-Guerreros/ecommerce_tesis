<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnidadMedidaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'umd_id'      => $this->umd_id,
            'umd_codigo'  => $this->umd_codigo,
            'umd_nombre'  => $this->umd_nombre,
            'umd_decimal' => $this->umd_decimal,
            'umd_estado'  => $this->umd_estado,
            'usos_count'  => $this->whenNotNull($this->usos_count),
        ];
    }
}