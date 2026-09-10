<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dep_id'        => $this->dep_id,
            'dep_descri'    => $this->dep_descri,
            'dep_estado'    => $this->dep_estado,
            'dep_creado'    => $this->dep_creado,

            // Atributos calculados en el controlador (contarUsos).
            'usos_count'   => $this->whenNotNull($this->usos_count),
            'usos_detalle' => $this->whenNotNull($this->usos_detalle),
        ];
    }
}
