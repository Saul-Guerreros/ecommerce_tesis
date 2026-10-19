<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fila del panel general A3: estado de los 4 ejes + flags de acciones. */
class PanelSolicitudResource extends JsonResource
{
    private const REASIGNABLES = ['RECIBIDA', 'EN_DIAGNOSTICO', 'PRESUPUESTADA', 'APROBADA', 'EN_REPARACION'];

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
            'dias_taller'=> $this->recepcion?->rse_fecha ? (int) $this->recepcion->rse_fecha->diffInDays(now()) : null,

            'ejes' => [
                'servicio' => ['codigo' => $estado, 'descri' => $this->estadoServicio?->ets_descri],
                'ingreso'  => $this->estadoIngreso?->elg_descri,
                'pago'     => $this->estadoPago ? ['codigo' => $this->estadoPago->epa_codigo, 'descri' => $this->estadoPago->epa_descri] : null,
                'egreso'   => $this->estadoEgreso?->elg_descri,
            ],
            'presupuesto' => $pres ? ['numero' => $pres->pse_numero, 'versio' => $pres->pse_versio,
                                      'estado' => $pres->estado?->epr_descri, 'fecven' => $pres->pse_fecven?->format('Y-m-d')] : null,
            'sse_total'  => (float) $this->sse_total,
            'tecnico'    => $tec ? ['usu_id' => $tec->usu_id, 'nombre' => $tec->persona?->nombre_completo] : null,

            'acciones' => [
                'reasignar'       => $this->recepcion !== null && in_array($estado, self::REASIGNABLES, true),
                'entregar'        => $estado === 'LISTA' && $this->entrega === null,
                'const_recepcion' => $this->recepcion !== null,
                'const_retiro'    => $this->entrega !== null,
            ],
        ];
    }
}
