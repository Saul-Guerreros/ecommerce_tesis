<?php

namespace App\Http\Resources\Funcionario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CargoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'crg_id'     => $this->crg_id,
            'crg_descr'  => $this->crg_descr,
            'crg_estado' => (int) $this->crg_estado,
            'crg_creado' => $this->crg_creado,
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}
