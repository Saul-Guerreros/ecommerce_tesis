<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Página de la orden para el técnico (T2; en los Pasos 4 y 5 suma
 * presupuesto y ejecución). Incluye los flags de qué acciones habilita
 * el estado actual, para que Angular no duplique la máquina de estados.
 */
class OrdenTrabajoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $estado = $this->estadoServicio?->ets_codigo;
        $orden  = $this->ordenServicio;
        $dia    = $orden?->diagnostico;
        $pres   = $orden?->presupuestoActual;
        $epr    = $pres?->estado?->epr_codigo;
        $esComplementario = $pres && $pres->pse_versio > 1;
        // Complementario sin resolver (borrador, en validación o enviado): bloquea el cierre.
        $complementarioPendiente = $esComplementario && in_array($epr, [
            EstadoPresupuesto::BORRADOR, EstadoPresupuesto::EN_REVISION, EstadoPresupuesto::ENVIADO,
        ], true);

        return [
            'sse_id'     => $this->sse_id,
            'sse_numero' => $this->sse_numero,
            'sse_garant' => $this->sse_garant,
            'estado'     => ['codigo' => $estado, 'descri' => $this->estadoServicio?->ets_descri],
            'cliente'    => $this->cliente?->persona?->nombre_completo,

            'equipo' => [
                'tdi_id'     => $this->sse_tdiid,
                'tipo'       => $this->tipoDispositivo?->tdi_descri,
                'sse_marca'  => $this->sse_marca,
                'sse_modelo' => $this->sse_modelo,
                'sse_nroser' => $this->sse_nroser,
            ],
            'sse_proble' => $this->sse_proble,

            'recepcion' => $this->recepcion ? [
                'rse_fecha'   => $this->recepcion->rse_fecha?->format('Y-m-d H:i'),
                'rse_estfis'  => $this->recepcion->rse_estfis,
                'rse_obs'     => $this->recepcion->rse_obs,
                'tiene_clave' => !is_null($this->recepcion->getRawOriginal('rse_clave')),
                'tecnico'     => $this->recepcion->tecnico?->persona?->nombre_completo,
                'accesorios'  => $this->recepcion->accesorios->pluck('rac_descri'),
            ] : null,

            'adjuntos' => SolicitudAdjuntoResource::collection($this->adjuntos),

            'orden' => $orden ? [
                'ose_id'     => $orden->ose_id,
                'ose_numero' => $orden->ose_numero,
                'ose_fecini' => $orden->ose_fecini?->format('Y-m-d H:i'),
                'ose_fecfin' => $orden->ose_fecfin?->format('Y-m-d H:i'),
                'ose_result' => $orden->ose_result,
                'ose_proced' => $orden->ose_proced,
            ] : null,

            'diagnostico' => $dia ? [
                'dia_causa'  => $dia->dia_causa,
                'dia_repara' => $dia->dia_repara,
                'dia_reqrep' => $dia->dia_reqrep,
                'dia_tiempo' => $dia->dia_tiempo,
                'dia_fecha'  => $dia->dia_fecha?->format('Y-m-d H:i'),
                'fallas'     => $dia->fallas->map(fn ($f) => [
                    'fll_id'     => $f->dfa_fllid,
                    'fll_descri' => $f->falla?->fll_descri,
                    'obs'        => $f->dfa_obs,
                ]),
            ] : null,

            'presupuesto' => $pres ? new PresupuestoResource($pres) : null,

            // Historial de versiones (la v1 aprobada sigue visible cuando hay complementario)
            'versiones' => $orden ? $orden->presupuestos->map(fn ($p) => [
                'pse_id'     => $p->pse_id,
                'pse_numero' => $p->pse_numero,
                'pse_versio' => $p->pse_versio,
                'estado'     => $p->estado?->epr_descri,
                'codigo'     => $p->estado?->epr_codigo,
                'pse_total'  => (float) $p->pse_total,
            ]) : [],

            // T4 - repuestos realmente usados
            'repuestos' => $orden ? $orden->repuestos->map(fn ($r) => [
                'osr_id'     => $r->osr_id,
                'descri'     => mb_substr((string) ($r->producto?->prd_descri ?? ''), 0, 150),
                'prd_codinv' => $r->producto?->prd_codinv,
                'osr_cantap' => (float) $r->osr_cantap,
                'osr_cantid' => (float) $r->osr_cantid,
                'osr_precio' => (float) $r->osr_precio,
                'osr_presup' => $r->osr_presup,
            ]) : [],
            'ose_ajuste' => (bool) $orden?->ose_ajuste,
            'ose_gardia' => $orden?->ose_gardia,

            'totales' => [
                'sse_subtot' => (float) $this->sse_subtot,
                'sse_cosret' => (float) $this->sse_cosret,
                'sse_total'  => (float) $this->sse_total,
            ],

            'acciones' => [
                'iniciar'          => $estado === EstadoServicio::RECIBIDA && !$orden,
                // El diagnóstico se congela cuando el presupuesto sale del borrador.
                'diagnosticar'     => $estado === EstadoServicio::EN_DIAGNOSTICO && $orden && !$orden->estaCerrada()
                                      && (!$pres || $epr === EstadoPresupuesto::BORRADOR),
                'cerrar_no_repara' => $estado === EstadoServicio::EN_DIAGNOSTICO && $dia && !$dia->dia_repara,
                // T3: editable mientras no exista o siga en BORRADOR
                'presupuestar'     => ($estado === EstadoServicio::EN_DIAGNOSTICO && $dia && $dia->dia_repara
                                          && (!$pres || $epr === EstadoPresupuesto::BORRADOR))
                                      // Paso 5: complementario abierto durante la reparación
                                      || ($estado === EstadoServicio::EN_REPARACION && $esComplementario
                                          && $epr === EstadoPresupuesto::BORRADOR),
                'enviar_presupuesto' => in_array($estado, [EstadoServicio::EN_DIAGNOSTICO, EstadoServicio::EN_REPARACION], true)
                                      && $epr === EstadoPresupuesto::BORRADOR && $pres->detalles->isNotEmpty(),
                // T4
                'iniciar_reparacion'       => $estado === EstadoServicio::APROBADA,
                'ejecutar'                 => $estado === EstadoServicio::EN_REPARACION && !$complementarioPendiente,
                'solicitar_complementario' => $estado === EstadoServicio::EN_REPARACION && $epr === EstadoPresupuesto::APROBADO,
                'descartar_complementario' => $estado === EstadoServicio::EN_REPARACION && $esComplementario
                                              && $epr === EstadoPresupuesto::BORRADOR,
            ],
        ];
    }
}