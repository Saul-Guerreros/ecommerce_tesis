<?php

namespace App\Services\Reporte;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte del Asistente Virtual (chatbot con IA). Mismo contrato ['filas', 'totales'].
 *
 * Fuentes: chat_conversacion (una por sesión de chat) y chat_mensaje (cada
 * turno: usuario, asistente o invocación de herramienta).
 *
 * Definiciones operativas (citables en la tesis):
 *   - Derivada ........ la conversación pidió atención humana: cnv_estado = 'derivada'
 *                       o tiene motivo de derivación (se conserva aunque luego se cierre).
 *   - Sin respuesta ... turno del asistente marcado con cms_sinres (Paso 2A): falló el
 *                       modelo o la búsqueda en el catálogo no encontró productos.
 *   - Tema ............ herramienta que el modelo decidió invocar (intención detectada).
 *   - Pregunta frecuente: texto del usuario normalizado (minúsculas, espacios simples).
 *                       Es coincidencia exacta; agrupar por similitud semántica
 *                       (embeddings) queda como trabajo futuro.
 *
 * El chatbot es único para toda la tienda: este reporte no filtra por sucursal.
 */
class ChatbotReporteService extends ConsultaReporte
{
    private const HERRAMIENTAS = [
        'buscar_productos'          => 'Búsqueda de productos',
        'ver_producto'              => 'Detalle de un producto',
        'proponer_agregar_carrito'  => 'Propuesta de agregar al carrito',
        'solicitar_atencion_humana' => 'Solicitud de atención humana',
    ];

    private const ES_DERIVADA = "(c.cnv_estado = 'derivada' OR c.cnv_motivo_deriv IS NOT NULL)";

    /** Texto del usuario normalizado para agrupar preguntas iguales. */
    private const NORMALIZADO = "LOWER(TRIM(regexp_replace(m.cms_contenido, '\\s+', ' ', 'g')))";

    private const MAX_FILAS_DETALLE = 500;

    // =====================================================================
    // 1. Conversaciones por período
    // =====================================================================

    public function porPeriodo(FiltrosReporte $f): array
    {
        $u = self::UNIDAD[$f->agrupacion] ?? 'month'; // lista blanca

        $sql = "
            WITH periodos AS (
                SELECT generate_series(date_trunc('{$u}', ?::date),
                                       date_trunc('{$u}', ?::date),
                                       interval '1 {$u}')::date AS periodo
            ),
            conv AS (
                SELECT date_trunc('{$u}', c.cnv_creado)::date AS periodo,
                       COUNT(*)                                          AS conversaciones,
                       COUNT(*) FILTER (WHERE c.usu_id IS NOT NULL)       AS identificadas,
                       COUNT(*) FILTER (WHERE " . self::ES_DERIVADA . ") AS derivadas,
                       COUNT(*) FILTER (WHERE EXISTS (
                           SELECT 1 FROM chat_mensaje x WHERE x.cnv_id = c.cnv_id AND x.cms_sinres
                       ))                                                AS con_sin_respuesta
                  FROM chat_conversacion c
                 WHERE c.cnv_creado BETWEEN ? AND ?
                 GROUP BY 1
            ),
            msg AS (
                SELECT date_trunc('{$u}', m.cms_creado)::date AS periodo,
                       COUNT(*) FILTER (WHERE m.cms_rol = 'usuario')                  AS mensajes,
                       COUNT(*) FILTER (WHERE m.cms_rol = 'asistente' AND m.cms_sinres) AS turnos_sin_respuesta,
                       COALESCE(SUM(COALESCE(m.cms_tokens_ent, 0) + COALESCE(m.cms_tokens_sal, 0)), 0) AS tokens,
                       AVG(m.cms_latencia_ms) FILTER (WHERE m.cms_rol = 'asistente')  AS latencia
                  FROM chat_mensaje m
                 WHERE m.cms_creado BETWEEN ? AND ?
                 GROUP BY 1
            )
            SELECT p.periodo,
                   COALESCE(c.conversaciones, 0)       AS conversaciones,
                   COALESCE(c.identificadas, 0)        AS identificadas,
                   COALESCE(c.derivadas, 0)            AS derivadas,
                   COALESCE(c.con_sin_respuesta, 0)    AS con_sin_respuesta,
                   COALESCE(m.mensajes, 0)             AS mensajes,
                   COALESCE(m.turnos_sin_respuesta, 0) AS turnos_sin_respuesta,
                   COALESCE(m.tokens, 0)               AS tokens,
                   m.latencia
              FROM periodos p
              LEFT JOIN conv c USING (periodo)
              LEFT JOIN msg m  USING (periodo)
             ORDER BY p.periodo";

        [$desde, $hasta]     = $f->rangoFechas();
        [$desdeTs, $hastaTs] = $f->rangoTimestamps();

        $filas = array_map(fn ($r) => [
            'periodo'              => $r->periodo,
            'etiqueta'             => $this->etiquetaPeriodo($r->periodo, $f->agrupacion),
            'conversaciones'       => (int) $r->conversaciones,
            'identificadas'        => (int) $r->identificadas,
            'mensajes'             => (int) $r->mensajes,
            'derivadas'            => (int) $r->derivadas,
            'tasa_derivacion'      => $this->porcentaje((int) $r->derivadas, (int) $r->conversaciones),
            'con_sin_respuesta'    => (int) $r->con_sin_respuesta,
            'tasa_sin_respuesta'   => $this->porcentaje((int) $r->con_sin_respuesta, (int) $r->conversaciones),
            'turnos_sin_respuesta' => (int) $r->turnos_sin_respuesta,
            'tokens'               => (int) $r->tokens,
            'latencia_ms'          => $r->latencia !== null ? (int) round((float) $r->latencia) : null,
        ], DB::select($sql, [$desde, $hasta, $desdeTs, $hastaTs, $desdeTs, $hastaTs]));

        $conversaciones = array_sum(array_column($filas, 'conversaciones'));
        $derivadas      = array_sum(array_column($filas, 'derivadas'));
        $sinRespuesta   = array_sum(array_column($filas, 'con_sin_respuesta'));
        $mensajes       = array_sum(array_column($filas, 'mensajes'));

        // Latencia global calculada en SQL (no promedio de promedios).
        $latencia = DB::table('chat_mensaje')
            ->where('cms_rol', 'asistente')
            ->whereBetween('cms_creado', [$desdeTs, $hastaTs])
            ->avg('cms_latencia_ms');

        return [
            'filas'   => $filas,
            'totales' => [
                'conversaciones'            => $conversaciones,
                'identificadas'             => array_sum(array_column($filas, 'identificadas')),
                'mensajes'                  => $mensajes,
                'mensajes_por_conversacion' => $conversaciones > 0 ? round($mensajes / $conversaciones, 1) : null,
                'derivadas'                 => $derivadas,
                'tasa_derivacion'           => $this->porcentaje($derivadas, $conversaciones),
                'con_sin_respuesta'         => $sinRespuesta,
                'tasa_sin_respuesta'        => $this->porcentaje($sinRespuesta, $conversaciones),
                'turnos_sin_respuesta'      => array_sum(array_column($filas, 'turnos_sin_respuesta')),
                'tokens'                    => array_sum(array_column($filas, 'tokens')),
                'latencia_ms'               => $latencia !== null ? (int) round((float) $latencia) : null,
            ],
        ];
    }

    // =====================================================================
    // 2. Temas (herramientas invocadas)
    // =====================================================================

    public function temas(FiltrosReporte $f): array
    {
        $filas = DB::table('chat_mensaje as m')
            ->where('m.cms_rol', 'herramienta')
            ->whereNotNull('m.cms_herramienta')
            ->whereBetween('m.cms_creado', $f->rangoTimestamps())
            ->selectRaw('m.cms_herramienta AS herramienta,
                COUNT(*)                 AS invocaciones,
                COUNT(DISTINCT m.cnv_id) AS conversaciones,
                MAX(m.cms_creado)        AS ultima')
            ->groupBy('m.cms_herramienta')
            ->orderByDesc('invocaciones')
            ->get()
            ->map(fn ($r) => [
                'herramienta'    => $r->herramienta,
                'tema'           => self::HERRAMIENTAS[$r->herramienta] ?? $r->herramienta,
                'invocaciones'   => (int) $r->invocaciones,
                'conversaciones' => (int) $r->conversaciones,
                'ultima'         => substr((string) $r->ultima, 0, 10),
            ])
            ->all();

        $invocaciones = array_sum(array_column($filas, 'invocaciones'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['invocaciones'], $invocaciones);
        }
        unset($fila);

        $mensajes = DB::table('chat_mensaje')
            ->where('cms_rol', 'usuario')
            ->whereBetween('cms_creado', $f->rangoTimestamps())
            ->count();

        return [
            'filas'   => $filas,
            'totales' => [
                'invocaciones'      => $invocaciones,
                'mensajes_usuario'  => $mensajes,
                'temas'             => count($filas),
            ],
        ];
    }

    // =====================================================================
    // 3. Preguntas más frecuentes
    // =====================================================================

    public function preguntas(FiltrosReporte $f): array
    {
        $filas = DB::table('chat_mensaje as m')
            ->where('m.cms_rol', 'usuario')
            ->whereNotNull('m.cms_contenido')
            ->whereBetween('m.cms_creado', $f->rangoTimestamps())
            ->whereRaw('LENGTH(TRIM(m.cms_contenido)) BETWEEN 3 AND 200')
            ->selectRaw(self::NORMALIZADO . ' AS pregunta,
                COUNT(*)                 AS veces,
                COUNT(DISTINCT m.cnv_id) AS conversaciones,
                MAX(m.cms_creado)        AS ultima')
            ->groupByRaw(self::NORMALIZADO)
            ->orderByDesc('veces')
            ->orderByDesc('ultima')
            ->limit($f->limite)
            ->get()
            ->map(fn ($r, $i) => [
                'posicion'       => $i + 1,
                'pregunta'       => $r->pregunta,
                'veces'          => (int) $r->veces,
                'conversaciones' => (int) $r->conversaciones,
                'ultima'         => substr((string) $r->ultima, 0, 10),
            ])
            ->all();

        $totalMensajes = DB::table('chat_mensaje')
            ->where('cms_rol', 'usuario')
            ->whereBetween('cms_creado', $f->rangoTimestamps())
            ->count();

        $veces = array_sum(array_column($filas, 'veces'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['veces'], $totalMensajes);
        }
        unset($fila);

        return [
            'filas'   => $filas,
            'totales' => [
                'preguntas'        => count($filas),
                'veces'            => $veces,
                'mensajes_usuario' => $totalMensajes,
                'cobertura'        => $this->porcentaje($veces, $totalMensajes),
            ],
        ];
    }

    // =====================================================================
    // 4. Consultas sin respuesta y derivadas
    // =====================================================================

    /**
     * Detalle para revisión: qué preguntó el usuario y qué pasó.
     *   - sin_respuesta: un turno del asistente con cms_sinres (pregunta = último
     *     mensaje del usuario antes de ese turno).
     *   - derivada: la conversación pidió atención humana (pregunta = último
     *     mensaje del usuario de la conversación; detalle = motivo).
     *
     * @param string $tipo 'todos' | 'sin_respuesta' | 'derivada'
     */
    public function sinRespuesta(FiltrosReporte $f, string $tipo = 'todos'): array
    {
        $rango = $f->rangoTimestamps();

        $ultimaPregunta = fn (string $condicion) => "(SELECT u.cms_contenido FROM chat_mensaje u
                WHERE u.cnv_id = %s AND u.cms_rol = 'usuario' {$condicion}
                ORDER BY u.cms_id DESC LIMIT 1)";

        $sinRespuesta = DB::table('chat_mensaje as m')
            ->where('m.cms_rol', 'asistente')
            ->where('m.cms_sinres', true)
            ->whereBetween('m.cms_creado', $rango)
            ->selectRaw("'sin_respuesta' AS tipo, m.cms_creado AS fecha, m.cnv_id AS conversacion, "
                . sprintf($ultimaPregunta('AND u.cms_id < m.cms_id'), 'm.cnv_id') . ' AS pregunta,
                LEFT(m.cms_contenido, 300) AS detalle');

        $derivadas = DB::table('chat_conversacion as c')
            ->whereRaw(self::ES_DERIVADA)
            ->whereBetween('c.cnv_creado', $rango)
            ->selectRaw("'derivada' AS tipo, c.cnv_ultimo_msj AS fecha, c.cnv_id AS conversacion, "
                . sprintf($ultimaPregunta(''), 'c.cnv_id') . " AS pregunta,
                COALESCE(c.cnv_motivo_deriv, 'Sin motivo registrado') AS detalle");

        $union = match ($tipo) {
            'sin_respuesta' => $sinRespuesta,
            'derivada'      => $derivadas,
            default         => $sinRespuesta->unionAll($derivadas),
        };

        $filas = DB::query()
            ->fromSub($union, 'x')
            ->orderByDesc('x.fecha')
            ->limit(self::MAX_FILAS_DETALLE)
            ->get()
            ->map(fn ($r) => [
                'tipo'         => $r->tipo,
                'tipo_texto'   => $r->tipo === 'derivada' ? 'Derivada' : 'Sin respuesta',
                'fecha'        => substr((string) $r->fecha, 0, 16),
                'conversacion' => (int) $r->conversacion,
                'pregunta'     => $r->pregunta ? mb_substr($r->pregunta, 0, 300) : null,
                'detalle'      => $r->detalle,
            ])
            ->all();

        return [
            'filas'   => $filas,
            'totales' => [
                'total'         => count($filas),
                'sin_respuesta' => count(array_filter($filas, fn ($x) => $x['tipo'] === 'sin_respuesta')),
                'derivadas'     => count(array_filter($filas, fn ($x) => $x['tipo'] === 'derivada')),
                'limite'        => self::MAX_FILAS_DETALLE,
            ],
        ];
    }
}
