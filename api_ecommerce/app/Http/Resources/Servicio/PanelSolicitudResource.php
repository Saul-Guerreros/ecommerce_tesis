<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\ServicioHistorialEstado;
use App\Services\Servicio\NotificadorServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fila del panel general A3: estado de los 4 ejes + flags de acciones. */
class PanelSolicitudResource extends JsonResource
{
    private const REASIGNABLES = ['RECIBIDA', 'EN_DIAGNOSTICO', 'PRESUPUESTADA', 'APROBADA', 'EN_REPARACION'];

    /** Último "Registrar contacto" (solo si se cargó la relación). */
    private function contacto(): ?array
    {
        if (!$this->relationLoaded('ultimoContacto') || !$this->ultimoContacto) {
            return null;
        }
        $c = $this->ultimoContacto;

        return [
            'fecha'     => $c->she_fecha?->format('Y-m-d H:i'),
            'medio'     => ServicioHistorialEstado::ETIQUETAS_MEDIO[$c->she_medio] ?? $c->she_medio,
            'resultado' => ServicioHistorialEstado::ETIQUETAS_RESULTADO[$c->she_result] ?? $c->she_result,
            'codigo'    => $c->she_result,
        ];
    }

    public function toArray(Request $request): array
    {
        $persona = $this->cliente?->persona;
        $tec     = $this->recepcion?->tecnico;
        $estado  = $this->estadoServicio?->ets_codigo;
        $pres    = $this->ordenServicio?->presupuestoActual;

        return [
            'sse_id'     => $this->sse_id,
            'sse_numero' => $this->sse_numero,
            'ose_numero' => $this->ordenServicio?->ose_numero,
            'sse_creado' => $this->sse_creado?->format('Y-m-d H:i'),
            'cliente'    => $persona?->nombre_completo,
            'equipo'     => trim(($this->tipoDispositivo?->tdi_descri ?? '') . ' ' . $this->sse_marca . ' ' . $this->sse_modelo),
            'sse_garant' => $this->sse_garant,
            'es_reclamo' => !is_null($this->sse_sseori),
            'canal'      => $this->sse_canal,
            'sucursal'   => $this->sse_sucid ? ['suc_id' => $this->sse_sucid, 'nombre' => $this->sucursal?->suc_nombre] : null,
            'dias_taller'=> $this->recepcion?->rse_fecha ? (int) $this->recepcion->rse_fecha->diffInDays(now()) : null,

            'ejes' => [
                'servicio' => ['codigo' => $estado, 'descri' => $this->estadoServicio?->ets_descri],
                'ingreso'  => $this->estadoIngreso?->elg_descri,
                'pago'     => $this->estadoPago ? ['codigo' => $this->estadoPago->epa_codigo, 'descri' => $this->estadoPago->epa_descri] : null,
                'egreso'   => $this->estadoEgreso?->elg_descri,
            ],
            'presupuesto' => $pres ? (new PresupuestoResumenResource($pres))->toArray($request) : null,
            'ultimo_contacto' => $this->contacto(),
            // Paso 2: presupuesto ENVIADO · Paso 3 (C.8): equipo LISTO para retirar
            'whatsapp_url'    => ($pres && $pres->estado?->epr_codigo === 'ENVIADO') || $estado === 'LISTA'
                ? app(NotificadorServicio::class)->enlaceWhatsapp($this->resource) : null,
            'sse_total'  => (float) $this->sse_total,
            'tecnico'    => $tec ? ['usu_id' => $tec->usu_id, 'nombre' => $tec->persona?->nombre_completo] : null,

            'acciones' => [
                'reasignar'       => $this->recepcion !== null && in_array($estado, self::REASIGNABLES, true),
                'entregar'        => $estado === 'LISTA' && $this->entrega === null,
                'const_recepcion' => $this->recepcion !== null,
                'const_retiro'    => $this->entrega !== null,
                // Paso 2: respuesta asistida, contacto y PDF del presupuesto
                'responder'       => (bool) ($pres && $pres->estado?->epr_codigo === 'ENVIADO'
                                     && !($pres->pse_fecven && $pres->pse_fecven->lt(today()))),
                'contacto'        => !in_array($estado, ['ENTREGADA', 'CANCELADA'], true),
                'pdf_presupuesto' => (bool) ($pres && in_array($pres->estado?->epr_codigo, ['ENVIADO', 'APROBADO', 'RECHAZADO'], true)),
            ],
        ];
    }
}
