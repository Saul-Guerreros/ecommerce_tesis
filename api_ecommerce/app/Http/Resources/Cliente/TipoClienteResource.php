<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpc_id'     => $this->tpc_id,
            'tpc_descri' => $this->tpc_descri,
            'tpc_estado' => $this->tpc_estado,
            'tpc_creado' => $this->tpc_creado,
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}
