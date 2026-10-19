<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Detalle completo para el panel admin. */
class SolicitudServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = $this->cliente?->persona;

        return [
            'sse_id'     => $this->sse_id,
            'sse_numero' => $this->sse_numero,
            'sse_creado' => $this->sse_creado?->format('Y-m-d H:i'),

            'cliente' => [
                'nombre'   => $persona?->nombre_completo,
                'cedula'   => $persona?->per_cedula,
                'telefono' => $persona?->contactos
                    ?->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])->first()?->con_valor,
                'correo'   => $persona?->contactos?->where('con_tipo', 'EMAIL')->first()?->con_valor,
            ],

            'equipo' => [
                'tipo'       => $this->tipoDispositivo?->tdi_descri,
                'sse_marca'  => $this->sse_marca,
                'sse_modelo' => $this->sse_modelo,
                'sse_nroser' => $this->sse_nroser,
            ],
            'sse_proble' => $this->sse_proble,
            'sse_acceso' => $this->sse_acceso,

            'sse_garant' => $this->sse_garant,
            'pedido'     => $this->whenLoaded('pedido', fn () => $this->pedido?->ped_numero),

            'ingreso' => [
                'a_domicilio' => $this->esADomicilio(),
                'modalidad'   => $this->esADomicilio() ? 'Retiro a domicilio' : 'Lo trae a la sucursal',
                'sucursal'    => $this->sucursal?->suc_nombre,
                'direccion'   => $this->direccion?->resumen,
                'referencia'  => $this->direccion?->dir_referencia,
                'sse_fecing'  => $this->sse_fecing?->format('Y-m-d'),
                'sse_cosret'  => (float) $this->sse_cosret,
            ],

            'estado_servicio' => ['codigo' => $this->estadoServicio?->ets_codigo, 'descri' => $this->estadoServicio?->ets_descri],
            'estado_ingreso'  => ['codigo' => $this->estadoIngreso?->elg_codigo, 'descri' => $this->estadoIngreso?->elg_descri],

            'adjuntos'  => SolicitudAdjuntoResource::collection($this->whenLoaded('adjuntos')),
            'recepcion' => $this->whenLoaded('recepcion', fn () => $this->recepcion
                ? new RecepcionServicioResource($this->recepcion) : null),
            'historial' => ServicioHistorialResource::collection($this->whenLoaded('historial')),
        ];
    }
}
