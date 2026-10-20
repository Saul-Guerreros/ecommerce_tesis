<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SucursalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'suc_id'        => $this->suc_id,
            'suc_empid'     => $this->suc_empid,
            'suc_ciuid'     => $this->suc_ciuid,
            'suc_nombre'    => $this->suc_nombre,
            'suc_princi'    => $this->suc_princi,
            'suc_establ'    => $this->suc_establ,
            'suc_direccion' => $this->suc_direccion,
            'suc_telef'     => $this->suc_telef,
            'suc_cel'       => $this->suc_cel,
            'suc_email'     => $this->suc_email,
            'suc_estado'    => $this->suc_estado,
            'suc_creado'    => $this->suc_creado,
            'suc_retiro'    => (bool) $this->suc_retiro,
            'suc_horario'   => $this->suc_horario,
            // Máscara lunes→domingo. suc_horario es el texto que ve el
            // cliente; esta es la que usa el cálculo del plazo de retiro.
            'suc_diaslab'   => $this->suc_diaslab ?? \App\Models\Configuracion\Sucursal::DIAS_LABORABLES_DEFECTO,
            'suc_webstk'    => (bool) $this->suc_webstk,     // <-- nuevo
            'suc_delivery'  => (bool) $this->suc_delivery,   // <-- nuevo

            'emp_razonsocial' => $this->whenLoaded('empresa', fn () => $this->empresa->emp_razonsocial),
            'ciu_descri'      => $this->whenLoaded('ciudad', fn () => $this->ciudad->ciu_descri),

            'depositos_count' => $this->whenNotNull($this->depositos_count),
        ];
    }
}