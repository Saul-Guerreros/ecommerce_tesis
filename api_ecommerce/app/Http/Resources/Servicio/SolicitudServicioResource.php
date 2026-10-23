<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\ServicioHistorialEstado;
use App\Services\Servicio\NotificadorServicio;
use App\Services\Servicio\SolicitudServicioService;
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
            'canal'      => $this->sse_canal,
            'registrado_por' => $this->whenLoaded('registradoPor', fn () => $this->registradoPor?->persona?->nombre_completo
                ?? $this->registradoPor?->usu_login),

            'cliente' => [
                'nombre'   => $persona?->nombre_completo,
                'cedula'   => $persona?->per_cedula,
                'telefono' => $persona?->contactos
                    ?->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])->first()?->con_valor,
                'correo'   => $persona?->contactos?->where('con_tipo', 'EMAIL')->first()?->con_valor,
                'whatsapp' => $persona?->contactos?->where('con_tipo', 'WHATSAPP')->first()?->con_valor,
                'tiene_cuenta' => $persona?->usuario !== null,
                // Aviso manual por WhatsApp (decisión 1-a): enlace wa.me con el mensaje del estado actual.
                'whatsapp_url' => app(NotificadorServicio::class)->enlaceWhatsapp($this->resource),
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
                'modalidad'   => $this->esMostrador() ? 'Ingreso en mostrador'
                    : ($this->esADomicilio() ? 'Retiro a domicilio' : 'Lo trae a la sucursal'),
                'suc_id'      => $this->sse_sucid,
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

            // Paso 2: presupuesto vigente y último contacto (botones Respuesta / Contacto / PDF)
            'presupuesto' => $this->ordenServicio?->presupuestoActual
                ? new PresupuestoResumenResource($this->ordenServicio->presupuestoActual) : null,
            'ultimo_contacto' => $this->ultimoContacto ? [
                'fecha'     => $this->ultimoContacto->she_fecha?->format('Y-m-d H:i'),
                'medio'     => ServicioHistorialEstado::ETIQUETAS_MEDIO[$this->ultimoContacto->she_medio] ?? null,
                'resultado' => ServicioHistorialEstado::ETIQUETAS_RESULTADO[$this->ultimoContacto->she_result] ?? null,
            ] : null,
            'cerrada' => in_array($this->estadoServicio?->ets_codigo, ['ENTREGADA', 'CANCELADA'], true),

            // Paso 3: reclamo de garantía presencial (botón del detalle -> wizard ?reclamo=)
            'garantia_vence' => $this->garantiaVenceEl()?->format('Y-m-d'),
            'puede_reclamar' => app(SolicitudServicioService::class)->esReclamable($this->resource),
            'es_reclamo_de'  => $this->solicitudOriginal?->sse_numero,
        ];
    }
}
