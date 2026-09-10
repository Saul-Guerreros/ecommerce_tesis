<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CiudadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ciu_id'     => $this->ciu_id,
            'ciu_depid'  => $this->ciu_depid,
            'ciu_descri' => $this->ciu_descri,
            'ciu_estado' => $this->ciu_estado,
            'ciu_creado' => $this->ciu_creado,

            // Se aplana el nombre del departamento porque el listado lo
            // muestra como una columna mas de la tabla, no como objeto anidado.
            'dep_descri' => $this->whenLoaded('departamento', fn () => $this->departamento->dep_descri),

            'usos_count'   => $this->whenNotNull($this->usos_count),
            'usos_detalle' => $this->whenNotNull($this->usos_detalle),
        ];
    }
}
