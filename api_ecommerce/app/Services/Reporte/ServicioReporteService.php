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
    // 6. Diagnóstico preliminar con IA
    // =====================================================================

    /**
     * Fecha de referencia: creación de la consulta (dpr_creado).
     * Acierto = (TOTAL + PARCIAL) / evaluados. Rango de costo: la estimación se compara
     * con la mano de obra (IVA incluido) de la última versión del presupuesto.
     */
    public function diagnosticoIa(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $manoObra = DB::table('presupuesto_servicio as ps')
            ->join('orden_servicio as os2', 'os2.ose_id', '=', 'ps.pse_oseid')
            ->join('presupuesto_detalle as pd', 'pd.psd_pseid', '=', 'ps.pse_id')
            ->where('pd.psd_tipo', 'MANO_OBRA')
            ->whereRaw('ps.pse_versio = (SELECT MAX(p2.pse_versio) FROM presupuesto_servicio p2 WHERE p2.pse_oseid = ps.pse_oseid)')
            ->groupBy('os2.ose_sseid')
            ->selectRaw('os2.ose_sseid AS sse_id, SUM(pd.psd_total) AS total');

        $q = DB::table('diagnostico_preliminar as dp')
            ->leftJoin('solicitud_servicio as ss', 'ss.sse_id', '=', 'dp.dpr_sseid')
            ->leftJoinSub($manoObra, 'mo', 'mo.sse_id', '=', 'dp.dpr_sseid')
            ->whereBetween('dp.dpr_creado', $f->rangoTimestamps());

        if ($tipoDispositivoId) {
            $q->where('dp.dpr_tdiid', $tipoDispositivoId);
        }
        $this->filtrarSolicitud($q, $f, null);

        $filas = $q->selectRaw("dp.dpr_canal AS canal,
                COUNT(*)                                                                 AS consultas,
                COUNT(*) FILTER (WHERE dp.dpr_estado = 'GENERADO')                      AS generados,
                COUNT(dp.dpr_sseid)                                                      AS vinculados,
                COUNT(dp.dpr_coinci)                                                     AS evaluados,
                COUNT(*) FILTER (WHERE dp.dpr_coinci = 'TOTAL')                          AS total,
                COUNT(*) FILTER (WHERE dp.dpr_coinci = 'PARCIAL')                        AS parcial,
                COUNT(*) FILTER (WHERE dp.dpr_coinci = 'NINGUNA')                        AS ninguna,
                COUNT(*) FILTER (WHERE dp.dpr_confia = 'BAJA')                           AS confianza_baja,
                COUNT(mo.total) FILTER (WHERE dp.dpr_cosmin IS NOT NULL)                 AS comparables,
                COUNT(*) FILTER (WHERE mo.total BETWEEN dp.dpr_cosmin AND dp.dpr_cosmax) AS en_rango,
                ROUND(AVG(dp.dpr_latms))                                                 AS latencia_ms")
            ->groupBy('dp.dpr_canal')
            ->orderBy('dp.dpr_canal')
            ->get()
            ->map(fn ($r) => [
                'canal'          => $r->canal,
                'consultas'      => (int) $r->consultas,
                'generados'      => (int) $r->generados,
                'vinculados'     => (int) $r->vinculados,
                'evaluados'      => (int) $r->evaluados,
                'total'          => (int) $r->total,
                'parcial'        => (int) $r->parcial,
                'ninguna'        => (int) $r->ninguna,
                'confianza_baja' => (int) $r->confianza_baja,
                'comparables'    => (int) $r->comparables,
                'en_rango'       => (int) $r->en_rango,
                'latencia_ms'    => $r->latencia_ms !== null ? (int) $r->latencia_ms : null,
            ])
            ->all();

        foreach ($filas as &$fila) {
            $fila['disponibilidad'] = $this->porcentaje($fila['generados'], $fila['consultas']);
            $fila['tasa_acierto']   = $this->porcentaje($fila['total'] + $fila['parcial'], $fila['evaluados']);
            $fila['tasa_total']     = $this->porcentaje($fila['total'], $fila['evaluados']);
            $fila['tasa_en_rango']  = $this->porcentaje($fila['en_rango'], $fila['comparables']);
        }
        unset($fila);

        $suma = fn (string $k) => array_sum(array_column($filas, $k));

        return [
            'filas'   => $filas,
            'totales' => [
                'consultas'     => $suma('consultas'),
                'evaluados'     => $suma('evaluados'),
                'tasa_acierto'  => $this->porcentaje($suma('total') + $suma('parcial'), $suma('evaluados')),
                'tasa_en_rango' => $this->porcentaje($suma('en_rango'), $suma('comparables')),
            ],
        ];
    }

    // =====================================================================
    // Punto 7. Clasificación automática de solicitudes (IA)
    // =====================================================================

    /**
     * Distribución de las solicitudes del período por tipo de problema, urgencia y
     * dispositivo (valores VIGENTES), más el acierto de la IA sobre las revisadas.
     * Revisada = confirmada/corregida por una persona o con diagnóstico del técnico.
     * Acierto categoría = IA vs. categoría de la falla principal del técnico (o la corregida).
     */
    public function clasificacion(FiltrosReporte $f, ?int $tipoDispositivoId = null): array
    {
        $base = function () use ($f, $tipoDispositivoId) {
            $q = DB::table('solicitud_servicio as ss')
                ->leftJoin('clasificacion_solicitud as cl', 'cl.cls_sseid', '=', 'ss.sse_id')
                ->whereBetween('ss.sse_creado', $f->rangoTimestamps());
            $this->filtrarSolicitud($q, $f, $tipoDispositivoId);

            return $q;
        };

        $dimensiones = [
            'categoria'   => ['categoria_falla', 'caf_id', 'cls_cafid', 'caf_codigo', 'caf_descri', "'info'"],
            'urgencia'    => ['urgencia_servicio', 'urg_id', 'cls_urgid', 'urg_codigo', 'urg_descri', 'cat.urg_color'],
            'dispositivo' => ['tipo_dispositivo', 'tdi_id', 'cls_tdiid', 'tdi_codigo', 'tdi_descri', "'primary'"],
        ];

        $filas = [];
        foreach ($dimensiones as $dimension => [$tabla, $pk, $fk, $codigo, $descri, $color]) {
            $grupo = $base()
                ->join("{$tabla} as cat", "cat.{$pk}", '=', "cl.{$fk}")
                ->whereIn('cl.cls_estado', ['IA', 'MANUAL'])
                ->selectRaw("cat.{$pk} AS id, cat.{$codigo} AS codigo, cat.{$descri} AS descri, {$color} AS color, COUNT(*) AS cantidad")
                ->groupByRaw("cat.{$pk}, cat.{$codigo}, cat.{$descri}" . ($dimension === 'urgencia' ? ', cat.urg_color, cat.urg_orden' : ''))
                ->orderByRaw($dimension === 'urgencia' ? 'cat.urg_orden' : 'COUNT(*) DESC')
                ->get();

            $total = (int) $grupo->sum('cantidad');
            foreach ($grupo as $r) {
                $filas[] = [
                    'dimension'     => $dimension,
                    'id'            => (int) $r->id,
                    'codigo'        => $r->codigo,
                    'descri'        => $r->descri,
                    'color'         => $r->color,
                    'cantidad'      => (int) $r->cantidad,
                    'participacion' => $this->porcentaje((int) $r->cantidad, $total),
                ];
            }
        }

        $t = $base()->selectRaw("
                COUNT(*)                                                                    AS solicitudes,
                COUNT(*) FILTER (WHERE cl.cls_estado IN ('IA', 'MANUAL'))                   AS clasificadas,
                COUNT(*) FILTER (WHERE cl.cls_tdiia IS NOT NULL)                            AS generadas_ia,
                COUNT(*) FILTER (WHERE cl.cls_estado = 'PENDIENTE' OR cl.cls_id IS NULL)    AS pendientes,
                COUNT(*) FILTER (WHERE cl.cls_estado = 'NO_DISPONIBLE')                     AS no_disponibles,
                COUNT(*) FILTER (WHERE cl.cls_estado = 'MANUAL')                            AS corregidas,
                COUNT(*) FILTER (WHERE cl.cls_confia = 'BAJA')                              AS confianza_baja,
                COUNT(*) FILTER (WHERE cl.cls_tdicli IS NULL AND cl.cls_tdiia IS NOT NULL)  AS dispositivo_por_ia,
                COUNT(*) FILTER (WHERE cl.cls_tdicli IS NOT NULL AND cl.cls_tdiia IS NOT NULL
                                   AND cl.cls_tdicli <> cl.cls_tdiia)                        AS inconsistentes,
                COUNT(*) FILTER (WHERE cl.cls_tdiia IS NOT NULL
                                   AND (cl.cls_revisa IS NOT NULL OR cl.cls_evalua IS NOT NULL)) AS revisadas,
                COUNT(*) FILTER (WHERE cl.cls_tdiia IS NOT NULL
                                   AND (cl.cls_revisa IS NOT NULL OR cl.cls_evalua IS NOT NULL)
                                   AND cl.cls_tdiia = cl.cls_tdiid)                          AS ok_dispositivo,
                COUNT(*) FILTER (WHERE cl.cls_tdiia IS NOT NULL
                                   AND (cl.cls_revisa IS NOT NULL OR cl.cls_evalua IS NOT NULL)
                                   AND cl.cls_urgia = cl.cls_urgid)                          AS ok_urgencia,
                COUNT(*) FILTER (WHERE cl.cls_tdiia IS NOT NULL
                                   AND (cl.cls_revisa IS NOT NULL OR cl.cls_evalua IS NOT NULL)
                                   AND cl.cls_cafia = COALESCE(cl.cls_cafdia, cl.cls_cafid)) AS ok_categoria,
                ROUND(AVG(cl.cls_latms) FILTER (WHERE cl.cls_tdiia IS NOT NULL))            AS latencia_ms
            ")->first();

        $revisadas = (int) $t->revisadas;
        $intentos  = (int) $t->generadas_ia + (int) $t->no_disponibles;

        return [
            'filas'   => $filas,
            'totales' => [
                'solicitudes'         => (int) $t->solicitudes,
                'clasificadas'        => (int) $t->clasificadas,
                'pendientes'          => (int) $t->pendientes,
                'no_disponibles'      => (int) $t->no_disponibles,
                'corregidas'          => (int) $t->corregidas,
                'confianza_baja'      => (int) $t->confianza_baja,
                'dispositivo_por_ia'  => (int) $t->dispositivo_por_ia,
                'inconsistentes'      => (int) $t->inconsistentes,
                'revisadas'           => $revisadas,
                'disponibilidad'      => $this->porcentaje((int) $t->generadas_ia, $intentos),
                'acierto_dispositivo' => $this->porcentaje((int) $t->ok_dispositivo, $revisadas),
                'acierto_urgencia'    => $this->porcentaje((int) $t->ok_urgencia, $revisadas),
                'acierto_categoria'   => $this->porcentaje((int) $t->ok_categoria, $revisadas),
                'latencia_ms'         => $t->latencia_ms !== null ? (int) $t->latencia_ms : null,
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
