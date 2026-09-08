<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cal_id'     => $this->cal_id,
            'cal_barid'  => $this->cal_barid,
            'cal_descri' => $this->cal_descri,
            'cal_estado' => $this->cal_estado,
            'cal_creado' => $this->cal_creado,

            'bar_descri' => $this->whenLoaded('barrio', fn () => $this->barrio->bar_descri),

            // ciu_descri no sale de una columna propia: se resuelve
            // navegando barrio -> ciudad. Requiere with('barrio.ciudad')
            // en el controlador; si no se precargo, el operador ?->
            // evita un error en vez de mostrar el campo vacio.
            'ciu_descri' => $this->whenLoaded('barrio', fn () => $this->barrio->ciudad?->ciu_descri),

            'usos_count'   => $this->whenNotNull($this->usos_count),
            'usos_detalle' => $this->whenNotNull($this->usos_detalle),
        ];
    }
}
