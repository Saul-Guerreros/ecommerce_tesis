<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rol_id'     => $this->rol_id,
            'rol_descri' => $this->rol_descri,
            'rol_estado' => $this->rol_estado,
            'rol_creado' => $this->rol_creado,

            // IDs de los permisos tildados (para precargar el checklist al editar)
            'permisos' => $this->whenLoaded('permisos', fn () => $this->permisos->pluck('prm_id')),

            'usuarios_count' => $this->whenNotNull($this->usuarios_count),
        ];
    }
}