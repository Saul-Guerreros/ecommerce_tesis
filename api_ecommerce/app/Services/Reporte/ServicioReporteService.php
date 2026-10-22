<?php

namespace App\Services\Reporte;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de Servicio Técnico. Mismo contrato ['filas', 'totales'].
 *
 * Fecha de referencia de cada vista (qué hecho cae en el período):
 *   - Estados ......... creación de la solicitud (sse_creado).
 *   - Técnicos ........ inicio de la orden (ose_fecini).
 *   - Tiempos ......... cierre de la orden (ose_fecfin): solo órdenes terminadas.
 *   - Fallas .......... fecha del diagnóstico (dia_fecha).
 *   - Presupuestos .... creación del presupuesto (pse_creado).
 *
 * Sucursal: sse_sucid es NULL cuando el equipo se retira a domicilio;
 * esas solicitudes se atribuyen a la sucursal principal (misma regla que pedidos).
 */
class ServicioReporteService extends ConsultaReporte
{
    private const CERRADOS = ['ENTREGADA', 'CANCELADA'];

    /** Nombre del técnico: persona del usuario o, si no tiene, su login. Alias: u, pe. */
    private const NOMBRE_TECNICO =
        "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pe.per_nombr1, pe.per_apell1)), ''), u.usu_login)";

    /** Duración en horas entre dos timestamps. */
    private const HORAS = 'EXTRACT(EPOCH FROM (%s - %s)) / 3600';

    // =====================================================================
    // 1. Solicitudes por estado
    // =====================================================================

    public function porEstado(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $q = DB::table('solicitud_servicio as ss')
            ->join('estado_servicio as es', 'es.ets_id', '=', 'ss.sse_etsid')
            ->whereBetween('ss.sse_creado', $f->rangoTimestamps());

        $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

        $filas = $q->selectRaw('es.ets_id, es.ets_codigo, es.ets_descri, es.ets_orden,
                    COUNT(*) AS cantidad, COALESCE(SUM(ss.sse_total), 0) AS monto')
            ->groupBy('es.ets_id', 'es.ets_codigo', 'es.ets_descri', 'es.ets_orden')
            ->orderBy('es.ets_orden')
            ->get()
            ->map(fn ($r) => [
                'id'      => (int) $r->ets_id,
                'codigo'  => $r->ets_codigo,
                'estado'  => $r->ets_descri,
                'grupo'   => in_array($r->ets_codigo, self::CERRADOS, true) ? 'cerrada' : 'abierta',
                'cantidad' => (int) $r->cantidad,
                'monto'   => $this->gs($r->monto),
            ])
            ->all();

        $total = array_sum(array_column($filas, 'cantidad'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['cantidad'], $total);
        }
        unset($fila);

        $contar = fn (string $codigo) => array_sum(array_column(
            array_filter($filas, fn ($x) => $x['codigo'] === $codigo), 'cantidad'));

        return [
            'filas'   => $filas,
            'totales' => [
                'cantidad'   => $total,
                'abiertas'   => array_sum(array_column(array_filter($filas, fn ($x) => $x['grupo'] === 'abierta'), 'cantidad')),
                'entregadas' => $contar('ENTREGADA'),
                'canceladas' => $contar('CANCELADA'),
                'monto'      => array_sum(array_column($filas, 'monto')),
            ],
        ];
    }

    // =====================================================================
    // 2. Órdenes por técnico
    // =====================================================================

    public function porTecnico(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $horas = sprintf(self::HORAS, 'os.ose_fecfin', 'os.ose_fecini');

        $q = DB::table('orden_servicio as os')
            ->join('solicitud_servicio as ss', 'ss.sse_id', '=', 'os.ose_sseid')
            ->join('usuario as u', 'u.usu_id', '=', 'os.ose_usuid')
            ->leftJoin('persona as pe', 'pe.per_id', '=', 'u.usu_perid')
            ->whereBetween('os.ose_fecini', $f->rangoTimestamps());

        $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

        $filas = $q->selectRaw('u.usu_id, ' . self::NOMBRE_TECNICO . " AS tecnico,
                    COUNT(*)                                                  AS asignadas,
                    COUNT(*) FILTER (WHERE os.ose_fecfin IS NOT NULL)          AS cerradas,
                    COUNT(*) FILTER (WHERE os.ose_fecfin IS NULL)              AS abiertas,
                    COUNT(*) FILTER (WHERE os.ose_result = 'REPARADO')         AS reparadas,
                    COUNT(*) FILTER (WHERE os.ose_result = 'PARCIAL')          AS parciales,
                    COUNT(*) FILTER (WHERE os.ose_result = 'NO_REPARABLE')     AS no_reparables,
                    AVG({$horas}) FILTER (WHERE os.ose_fecfin IS NOT NULL)     AS horas")
            ->groupBy('u.usu_id', 'pe.per_nombr1', 'pe.per_apell1', 'u.usu_login')
            ->orderByDesc('asignadas')
            ->get()
            ->map(fn ($r) => [
                'id'            => (int) $r->usu_id,
                'tecnico'       => $r->tecnico,
                'asignadas'     => (int) $r->asignadas,
                'cerradas'      => (int) $r->cerradas,
                'abiertas'      => (int) $r->abiertas,
                'reparadas'     => (int) $r->reparadas,
                'parciales'     => (int) $r->parciales,
                'no_reparables' => (int) $r->no_reparables,
                'efectividad'   => $this->porcentaje((int) $r->reparadas, (int) $r->cerradas),
                'horas'         => $r->horas !== null ? round((float) $r->horas, 1) : null,
            ])
            ->all();

        $cerradas  = array_sum(array_column($filas, 'cerradas'));
        $reparadas = array_sum(array_column($filas, 'reparadas'));

        // Promedio ponderado por órdenes cerradas (no promedio de promedios).
        $horasPonderadas = array_sum(array_map(fn ($x) => ($x['horas'] ?? 0) * $x['cerradas'], $filas));

        return [
            'filas'   => $filas,
            'totales' => [
                'tecnicos'      => count($filas),
                'asignadas'     => array_sum(array_column($filas, 'asignadas')),
                'cerradas'      => $cerradas,
                'abiertas'      => array_sum(array_column($filas, 'abiertas')),
                'reparadas'     => $reparadas,
                'parciales'     => array_sum(array_column($filas, 'parciales')),
                'no_reparables' => array_sum(array_column($filas, 'no_reparables')),
                'efectividad'   => $this->porcentaje($reparadas, $cerradas),
                'horas'         => $cerradas > 0 ? round($horasPonderadas / $cerradas, 1) : null,
            ],
        ];
    }

    // =====================================================================
    // 3. Tiempo promedio de reparación
    // =====================================================================

    /**
     * Dos medidas, ambas solo sobre órdenes CERRADAS en el período:
     *   - Horas de taller: inicio → cierre de la orden (trabajo técnico).
     *   - Días totales:    recepción del equipo → entrega al cliente
     *                      (lo que percibe el cliente; solo si ya se entregó).
     */
    public function tiempos(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $horas = sprintf(self::HORAS, 'os.ose_fecfin', 'os.ose_fecini');
        $dias  = 'EXTRACT(EPOCH FROM (en.ese_fecha - rs.rse_fecha)) / 86400';

        $base = function () use ($f, $tipoDispositivoId) {
            $q = DB::table('orden_servicio as os')
                ->join('solicitud_servicio as ss', 'ss.sse_id', '=', 'os.ose_sseid')
                ->join('tipo_dispositivo as td', 'td.tdi_id', '=', 'ss.sse_tdiid')
                ->leftJoin('recepcion_servicio as rs', 'rs.rse_sseid', '=', 'ss.sse_id')
                ->leftJoin('entrega_servicio as en', 'en.ese_sseid', '=', 'ss.sse_id')
                ->whereNotNull('os.ose_fecfin')
                ->whereBetween('os.ose_fecfin', $f->rangoTimestamps());

            $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

            return $q;
        };

        $metricas = "COUNT(*)                     AS ordenes,
                     AVG({$horas})                AS horas_promedio,
                     MIN({$horas})                AS horas_min,
                     MAX({$horas})                AS horas_max,
                     COUNT(en.ese_id)             AS entregadas,
                     AVG({$dias}) FILTER (WHERE en.ese_fecha IS NOT NULL AND rs.rse_fecha IS NOT NULL) AS dias_total";

        $filas = $base()
            ->selectRaw("td.tdi_id, td.tdi_descri, {$metricas}")
            ->groupBy('td.tdi_id', 'td.tdi_descri')
            ->orderByDesc('ordenes')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->tdi_id, 'tipo' => $r->tdi_descri, ...$this->metricasTiempo($r)])
            ->all();

        // Totales calculados en SQL sobre todas las órdenes (promedio real, no promedio de promedios).
        $global = $base()->selectRaw($metricas)->first();

        return [
            'filas'   => $filas,
            'totales' => $global && (int) $global->ordenes > 0 ? $this->metricasTiempo($global) : [
                'ordenes' => 0, 'entregadas' => 0, 'horas_promedio' => null,
                'horas_min' => null, 'horas_max' => null, 'dias_total' => null,
            ],
        ];
    }

    // =====================================================================
    // 4. Fallas frecuentes
    // =====================================================================

    public function fallas(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $base = function () use ($f, $tipoDispositivoId) {
            $q = DB::table('diagnostico_falla as df')
                ->join('diagnostico as di', 'di.dia_id', '=', 'df.dfa_diaid')
                ->join('falla as fa', 'fa.fll_id', '=', 'df.dfa_fllid')
                ->join('orden_servicio as os', 'os.ose_id', '=', 'di.dia_oseid')
                ->join('solicitud_servicio as ss', 'ss.sse_id', '=', 'os.ose_sseid')
                ->whereBetween('di.dia_fecha', $f->rangoTimestamps());

            $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

            return $q;
        };

        $filas = $base()
            ->leftJoin('tipo_dispositivo as td', 'td.tdi_id', '=', 'fa.fll_tdiid')
            ->selectRaw("fa.fll_id, fa.fll_codigo, fa.fll_descri,
                COALESCE(td.tdi_descri, 'General')                         AS aplica,
                COUNT(*)                                                    AS ocurrencias,
                COUNT(*) FILTER (WHERE os.ose_result = 'REPARADO')          AS reparadas,
                COUNT(*) FILTER (WHERE di.dia_repara = false)               AS irreparables")
            ->groupBy('fa.fll_id', 'fa.fll_codigo', 'fa.fll_descri', 'td.tdi_descri')
            ->orderByDesc('ocurrencias')
            ->get()
            ->map(fn ($r) => [
                'id'           => (int) $r->fll_id,
                'codigo'       => $r->fll_codigo,
                'falla'        => $r->fll_descri,
                'aplica'       => $r->aplica,
                'ocurrencias'  => (int) $r->ocurrencias,
                'reparadas'    => (int) $r->reparadas,
                'irreparables' => (int) $r->irreparables,
            ])
            ->all();

        $ocurrencias = array_sum(array_column($filas, 'ocurrencias'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['ocurrencias'], $ocurrencias);
            $fila['tasa_reparacion'] = $this->porcentaje($fila['reparadas'], $fila['ocurrencias']);
        }
        unset($fila);

        return [
            'filas'   => $filas,
            'totales' => [
                'fallas'        => count($filas),
                'ocurrencias'   => $ocurrencias,
                'diagnosticos'  => (int) $base()->distinct()->count('di.dia_id'),
                'reparadas'     => array_sum(array_column($filas, 'reparadas')),
                'irreparables'  => array_sum(array_column($filas, 'irreparables')),
            ],
        ];
    }

    // =====================================================================
    // 5. Presupuestos aprobados / rechazados
    // =====================================================================

    /**
     * Cada versión de presupuesto cuenta como una decisión del cliente.
     * Tasa de aprobación = aprobados / (aprobados + rechazados): los que
     * siguen en borrador, enviados o en revisión todavía no tienen respuesta.
     */
    public function presupuestos(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $respuesta = sprintf(self::HORAS, 'ps.pse_respon', 'ps.pse_enviad');

        $base = function () use ($f, $tipoDispositivoId) {
            $q = DB::table('presupuesto_servicio as ps')
                ->join('estado_presupuesto as ep', 'ep.epr_id', '=', 'ps.pse_eprid')
                ->join('orden_servicio as os', 'os.ose_id', '=', 'ps.pse_oseid')
                ->join('solicitud_servicio as ss', 'ss.sse_id', '=', 'os.ose_sseid')
                ->whereBetween('ps.pse_creado', $f->rangoTimestamps());

            $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

            return $q;
        };

        $filas = $base()
            ->selectRaw("ep.epr_id, ep.epr_codigo, ep.epr_descri,
                COUNT(*)                                     AS cantidad,
                COALESCE(SUM(ps.pse_total), 0)               AS monto,
                AVG({$respuesta}) FILTER (WHERE ps.pse_respon IS NOT NULL AND ps.pse_enviad IS NOT NULL) AS horas_respuesta")
            ->groupBy('ep.epr_id', 'ep.epr_codigo', 'ep.epr_descri')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn ($r) => [
                'id'              => (int) $r->epr_id,
                'codigo'          => $r->epr_codigo,
                'estado'          => $r->epr_descri,
                'cantidad'        => (int) $r->cantidad,
                'monto'           => $this->gs($r->monto),
                'horas_respuesta' => $r->horas_respuesta !== null ? round((float) $r->horas_respuesta, 1) : null,
            ])
            ->all();

        $cantidad = array_sum(array_column($filas, 'cantidad'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['cantidad'], $cantidad);
        }
        unset($fila);

        $de = fn (string $codigo, string $campo) => array_sum(array_column(
            array_filter($filas, fn ($x) => $x['codigo'] === $codigo), $campo));

        $aprobados  = $de('APROBADO', 'cantidad');
        $rechazados = $de('RECHAZADO', 'cantidad');

        $respuestaGlobal = $base()
            ->whereNotNull('ps.pse_respon')
            ->whereNotNull('ps.pse_enviad')
            ->selectRaw("AVG({$respuesta}) AS horas")
            ->value('horas');

        return [
            'filas'   => $filas,
            'totales' => [
                'cantidad'         => $cantidad,
                'aprobados'        => $aprobados,
                'rechazados'       => $rechazados,
                'vencidos'         => $de('VENCIDO', 'cantidad'),
                'pendientes'       => $cantidad - $aprobados - $rechazados - $de('VENCIDO', 'cantidad'),
                'tasa_aprobacion'  => $this->porcentaje($aprobados, $aprobados + $rechazados),
                'monto'            => array_sum(array_column($filas, 'monto')),
                'monto_aprobado'   => $de('APROBADO', 'monto'),
                'monto_rechazado'  => $de('RECHAZADO', 'monto'),
                'horas_respuesta'  => $respuestaGlobal !== null ? round((float) $respuestaGlobal, 1) : null,
            ],
        ];
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /** Sucursal (NULL = principal) y tipo de dispositivo sobre solicitud_servicio (alias ss). */
    private function filtrarSolicitud(Builder $q, FiltrosReporte $f, ?int $tipoDispositivoId): void
    {
        if ($tipoDispositivoId) {
            $q->where('ss.sse_tdiid', $tipoDispositivoId);
        }

        if (!$f->sucursalId) {
            return;
        }

        if ($f->sucursalId === $this->sucursalPrincipalId()) {
            $q->where(fn (Builder $w) => $w->where('ss.sse_sucid', $f->sucursalId)->orWhereNull('ss.sse_sucid'));
            return;
        }

        $q->where('ss.sse_sucid', $f->sucursalId);
    }

    private function metricasTiempo(object $r): array
    {
        $redondear = fn ($v) => $v !== null ? round((float) $v, 1) : null;

        return [
            'ordenes'        => (int) $r->ordenes,
            'entregadas'     => (int) $r->entregadas,
            'horas_promedio' => $redondear($r->horas_promedio),
            'horas_min'      => $redondear($r->horas_min),
            'horas_max'      => $redondear($r->horas_max),
            'dias_total'     => $redondear($r->dias_total),
        ];
    }
}
