<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepositoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dpo_id'     => $this->dpo_id,
            'dpo_sucid'  => $this->dpo_sucid,
            'dpo_codigo' => $this->dpo_codigo,
            'dpo_nombre' => $this->dpo_nombre,
            'dpo_descri' => $this->dpo_descri,
            'dpo_princi' => $this->dpo_princi,
            'dpo_estado' => $this->dpo_estado,
            'dpo_creado' => $this->dpo_creado,

            // Cadena deposito -> sucursal -> empresa. Requiere
            // with('sucursal.empresa') en el controlador; whenLoaded()
            // anidado evita el acceso directo que prohíbe el Estándar.
            'suc_nombre'      => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
            'emp_razonsocial' => $this->whenLoaded('sucursal', fn () => $this->sucursal->relationLoaded('empresa')
                ? $this->sucursal->empresa->emp_razonsocial
                : null),

            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}