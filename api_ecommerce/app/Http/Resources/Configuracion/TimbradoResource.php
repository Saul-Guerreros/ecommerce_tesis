<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimbradoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tim_id'          => $this->tim_id,
            'tim_tcoid'       => $this->tim_tcoid,
            'tim_sucid'       => $this->tim_sucid,
            'tim_numero'      => $this->tim_numero,
            'tim_establec'    => $this->tim_establec,
            'tim_puntexp'     => $this->tim_puntexp,
            'tim_fechin'      => $this->tim_fechin?->format('Y-m-d'),
            'tim_fechfn'      => $this->tim_fechfn?->format('Y-m-d'),
            'tim_nroini'      => $this->tim_nroini,
            'tim_nrofin'      => $this->tim_nrofin,
            'tim_nroact'      => $this->tim_nroact,
            'tim_observacion' => $this->tim_observacion,
            'tim_estado'      => $this->tim_estado,
            'tim_creado'      => $this->tim_creado,

            // vigente cruza estado manual + rango de fechas (ver accesor en el modelo).
            'vigente' => $this->vigente,

            // ya se emitieron documentos con este timbrado -> no puede borrarse.
            'en_uso' => $this->tim_nroact != $this->tim_nroini,

            'tco_descri' => $this->whenLoaded('tipoComprobante', fn () => $this->tipoComprobante->tco_descri),
            'tco_codigo' => $this->whenLoaded('tipoComprobante', fn () => $this->tipoComprobante->tco_codigo),
            'suc_nombre' => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
        ];
    }
}