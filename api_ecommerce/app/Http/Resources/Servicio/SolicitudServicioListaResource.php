<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fila de la bandeja A1 (liviana: sin adjuntos ni historial). */
class SolicitudServicioListaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = $this->cliente?->persona;

        return [
            'sse_id'      => $this->sse_id,
            'sse_numero'  => $this->sse_numero,
            'sse_creado'  => $this->sse_creado?->format('Y-m-d H:i'),
            'cliente'     => $persona?->nombre_completo,
            'cedula'      => $persona?->per_cedula,
            'equipo'      => trim(($this->tipoDispositivo?->tdi_descri ?? '') . ' ' . $this->sse_marca . ' ' . $this->sse_modelo),
            'sse_garant'  => $this->sse_garant,
            'a_domicilio' => $this->esADomicilio(),
            'sse_fecing'  => $this->sse_fecing?->format('Y-m-d'),
            'canal'       => $this->sse_canal,
            'sucursal'    => $this->sucursal?->suc_nombre,
            'estado_servicio' => [
                'codigo' => $this->estadoServicio?->ets_codigo,
                'descri' => $this->estadoServicio?->ets_descri,
            ],
            'estado_ingreso' => [
                'codigo' => $this->estadoIngreso?->elg_codigo,
                'descri' => $this->estadoIngreso?->elg_descri,
            ],
            'clasificacion' => $this->whenLoaded('clasificacion', fn () => $this->clasificacion
                ? new ClasificacionSolicitudResource($this->clasificacion)
                : null),
            'tecnico' => $this->whenLoaded('recepcion', fn () => $this->recepcion?->tecnico?->persona
                ? trim($this->recepcion->tecnico->persona->per_nombr1 . ' ' . $this->recepcion->tecnico->persona->per_apell1)
                : null),
        ];
    }
}
