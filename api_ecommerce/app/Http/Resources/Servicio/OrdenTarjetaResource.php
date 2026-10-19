<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Tarjeta del tablero T1. */
class OrdenTarjetaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tec = $this->recepcion?->tecnico?->persona;

        return [
            'sse_id'      => $this->sse_id,
            'sse_numero'  => $this->sse_numero,
            'ose_numero'  => $this->ordenServicio?->ose_numero,
            'equipo'      => trim(($this->tipoDispositivo?->tdi_descri ?? '') . ' ' . $this->sse_marca . ' ' . $this->sse_modelo),
            'sse_garant'  => $this->sse_garant,
            'estado'      => $this->estadoServicio?->ets_codigo,
            'recibido'    => $this->recepcion?->rse_fecha?->format('Y-m-d H:i'),
            'tecnico'     => $tec ? trim($tec->per_nombr1 . ' ' . $tec->per_apell1) : null,
        ];
    }
}
