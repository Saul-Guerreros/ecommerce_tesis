<?php

namespace App\Services\Reporte;

use App\Models\Venta\VentaCab;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de Clientes. Mismo contrato ['filas', 'totales'].
 *
 * Toda compra se mide con facturas vigentes (pedidos pagados), igual que
 * Ventas y el dashboard: un cliente "compró" cuando su pedido se concretó.
 */
class ClientesReporteService extends ConsultaReporte
{
    /** Nombre completo desde persona; alias cl, pe. */
    private const NOMBRE = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pe.per_nombr1, pe.per_nombr2, pe.per_apell1, pe.per_apell2)), ''), 'Cliente #' || cl.cli_id)";

    /** RUC si lo tiene; si no, cédula. */
    private const DOCUMENTO = 'COALESCE(cl.cli_ruc, pe.per_cedula)';

    /** Tramos de inactividad (días desde la última compra). */
    private const TRAMOS_INACTIVIDAD = [
        ['etiqueta' => 'Hasta 180 días',  'min' => 0,   'max' => 180],
        ['etiqueta' => '181 a 365 días',  'min' => 181, 'max' => 365],
        ['etiqueta' => 'Más de un año',   'min' => 366, 'max' => null],
    ];

    // =====================================================================
    // 1. Top clientes
    // =====================================================================

    public function top(FiltrosReporte $f, ?int $tipoClienteId = null): array
    {
        $base = fn () => $this->facturasVigentes($f)
            ->join('cliente as cl', 'cl.cli_id', '=', 'vc.ven_cliid')
            ->when($tipoClienteId, fn (Builder $q, int $id) => $q->where('cl.cli_tpcid', $id));

        $filas = $base()
            ->join('persona as pe', 'pe.per_id', '=', 'cl.cli_perid')
            ->leftJoin('tipo_cliente as tc', 'tc.tpc_id', '=', 'cl.cli_tpcid')
            ->selectRaw('cl.cli_id, ' . self::NOMBRE . ' AS cliente, ' . self::DOCUMENTO . " AS documento,
                COALESCE(tc.tpc_descri, '—') AS tipo,
                COUNT(*)             AS compras,
                SUM(vc.ven_total)    AS total,
                MAX(vc.ven_fchaem)   AS ultima_compra")
            ->groupBy('cl.cli_id', 'cl.cli_ruc', 'pe.per_nombr1', 'pe.per_nombr2', 'pe.per_apell1',
                'pe.per_apell2', 'pe.per_cedula', 'tc.tpc_descri')
            ->orderByDesc('total')
            ->limit($f->limite)
            ->get()
            ->map(fn ($r) => [
                'id'            => (int) $r->cli_id,
                'cliente'       => $r->cliente,
                'documento'     => $r->documento,
                'tipo'          => $r->tipo,
                'compras'       => (int) $r->compras,
                'total'         => $this->gs($r->total),
                'ticket'        => $r->compras > 0 ? $this->gs($r->total / $r->compras) : 0,
                'ultima_compra' => $r->ultima_compra,
            ])
            ->all();

        // Universo del período (no solo el top) para medir concentración.
        $universo = $base()->selectRaw('COUNT(DISTINCT vc.ven_cliid) AS clientes, COALESCE(SUM(vc.ven_total), 0) AS total')->first();
        $totalPeriodo = (float) $universo->total;

        foreach ($filas as $i => &$fila) {
            $fila['posicion']      = $i + 1;
            $fila['participacion'] = $this->porcentaje($fila['total'], $totalPeriodo);
        }
        unset($fila);

        $totalTop = array_sum(array_column($filas, 'total'));

        return [
            'filas'   => $filas,
            'totales' => [
                'clientes'        => count($filas),
                'compras'         => array_sum(array_column($filas, 'compras')),
                'total'           => $totalTop,
                'clientes_activos' => (int) $universo->clientes,
                'total_periodo'   => $this->gs($totalPeriodo),
                'concentracion'   => $this->porcentaje($totalTop, $totalPeriodo),
            ],
        ];
    }

    // =====================================================================
    // 2. Clientes nuevos por período
    // =====================================================================

    /**
     * Por cada período (día/mes/año):
     *   - Registrados: clientes dados de alta (cli_creado). No depende de la sucursal.
     *   - Compradores nuevos: clientes cuya PRIMERA compra cae en ese período.
     *   - Recurrentes: clientes que compraron en el período y ya habían comprado antes.
     * Nuevos y recurrentes son excluyentes dentro de un mismo período.
     */
    public function nuevos(FiltrosReporte $f): array
    {
        $u   = self::UNIDAD[$f->agrupacion] ?? 'month'; // lista blanca
        $suc = $f->sucursalId ? 'AND vc.ven_sucid = ?' : '';

        $sql = "
            WITH periodos AS (
                SELECT generate_series(date_trunc('{$u}', ?::date),
                                       date_trunc('{$u}', ?::date),
                                       interval '1 {$u}')::date AS periodo
            ),
            reg AS (
                SELECT date_trunc('{$u}', cli_creado)::date AS periodo, COUNT(*) AS registrados
                  FROM cliente
                 WHERE cli_creado BETWEEN ? AND ?
                 GROUP BY 1
            ),
            primeras AS (
                SELECT ven_cliid, MIN(ven_fchaem) AS primera
                  FROM venta_cab
                 WHERE ven_estado <> ? AND ven_cliid IS NOT NULL
                 GROUP BY 1
            ),
            comp AS (
                SELECT date_trunc('{$u}', vc.ven_fchaem)::date AS periodo,
                       COUNT(DISTINCT vc.ven_cliid) FILTER (
                           WHERE date_trunc('{$u}', p.primera) = date_trunc('{$u}', vc.ven_fchaem)) AS nuevos,
                       COUNT(DISTINCT vc.ven_cliid) FILTER (
                           WHERE date_trunc('{$u}', p.primera) < date_trunc('{$u}', vc.ven_fchaem)) AS recurrentes
                  FROM venta_cab vc
                  JOIN primeras p ON p.ven_cliid = vc.ven_cliid
                 WHERE vc.ven_estado <> ? AND vc.ven_fchaem BETWEEN ? AND ? {$suc}
                 GROUP BY 1
            )
            SELECT p.periodo,
                   COALESCE(r.registrados, 0) AS registrados,
                   COALESCE(c.nuevos, 0)      AS nuevos,
                   COALESCE(c.recurrentes, 0) AS recurrentes
              FROM periodos p
              LEFT JOIN reg r  USING (periodo)
              LEFT JOIN comp c USING (periodo)
             ORDER BY p.periodo";

        [$desde, $hasta]     = $f->rangoFechas();
        [$desdeTs, $hastaTs] = $f->rangoTimestamps();

        $bindings = [
            $desde, $hasta,
            $desdeTs, $hastaTs,
            VentaCab::ESTADO_ANULADA,
            VentaCab::ESTADO_ANULADA, $desde, $hasta,
        ];
        if ($f->sucursalId) {
            $bindings[] = $f->sucursalId;
        }

        $filas = array_map(fn ($r) => [
            'periodo'     => $r->periodo,
            'etiqueta'    => $this->etiquetaPeriodo($r->periodo, $f->agrupacion),
            'registrados' => (int) $r->registrados,
            'nuevos'      => (int) $r->nuevos,
            'recurrentes' => (int) $r->recurrentes,
            'activos'     => (int) $r->nuevos + (int) $r->recurrentes,
        ], DB::select($sql, $bindings));

        return [
            'filas'   => $filas,
            'totales' => [
                'registrados' => array_sum(array_column($filas, 'registrados')),
                'nuevos'      => array_sum(array_column($filas, 'nuevos')),
                'recurrentes' => array_sum(array_column($filas, 'recurrentes')),
                'activos'     => array_sum(array_column($filas, 'activos')),
            ],
        ];
    }

    // =====================================================================
    // 3. Clientes inactivos
    // =====================================================================

    /**
     * Clientes activos que ya compraron alguna vez, pero cuya última compra
     * es anterior a hoy − $dias. Foto actual (no usa el rango de fechas).
     * Incluye datos de contacto para campañas de reactivación.
     */
    public function inactivos(FiltrosReporte $f, int $dias = 90, ?int $tipoClienteId = null): array
    {
        $limite = now()->subDays($dias)->toDateString();

        $historial = DB::table('venta_cab')
            ->where('ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('ven_cliid')
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('ven_sucid', $id))
            ->groupBy('ven_cliid')
            ->havingRaw('MAX(ven_fchaem) < ?', [$limite])
            ->selectRaw('ven_cliid, MAX(ven_fchaem) AS ultima, COUNT(*) AS compras, SUM(ven_total) AS total');

        $contactos = DB::table('contacto')
            ->where('con_estado', 1)
            ->groupBy('con_perid')
            ->selectRaw("con_perid,
                MAX(con_valor) FILTER (WHERE con_tipo = 'EMAIL')                                AS email,
                MAX(con_valor) FILTER (WHERE con_tipo IN ('CELULAR', 'WHATSAPP', 'TELEFONO'))   AS telefono");

        $filas = DB::query()
            ->fromSub($historial, 'h')
            ->join('cliente as cl', 'cl.cli_id', '=', 'h.ven_cliid')
            ->join('persona as pe', 'pe.per_id', '=', 'cl.cli_perid')
            ->leftJoin('tipo_cliente as tc', 'tc.tpc_id', '=', 'cl.cli_tpcid')
            ->leftJoinSub($contactos, 'ct', 'ct.con_perid', '=', 'pe.per_id')
            ->where('cl.cli_estado', 1)
            ->when($tipoClienteId, fn (Builder $q, int $id) => $q->where('cl.cli_tpcid', $id))
            ->selectRaw('cl.cli_id, ' . self::NOMBRE . ' AS cliente, ' . self::DOCUMENTO . " AS documento,
                COALESCE(tc.tpc_descri, '—') AS tipo, ct.email, ct.telefono,
                h.ultima, (CURRENT_DATE - h.ultima) AS dias, h.compras, h.total")
            ->orderByDesc('h.total')
            ->get()
            ->map(function ($r) {
                $dias = (int) $r->dias;

                return [
                    'id'            => (int) $r->cli_id,
                    'cliente'       => $r->cliente,
                    'documento'     => $r->documento,
                    'tipo'          => $r->tipo,
                    'email'         => $r->email,
                    'telefono'      => $r->telefono,
                    'ultima_compra' => $r->ultima,
                    'dias'          => $dias,
                    'tramo'         => $this->tramo($dias),
                    'compras'       => (int) $r->compras,
                    'total'         => $this->gs($r->total),
                    'ticket'        => $r->compras > 0 ? $this->gs($r->total / $r->compras) : 0,
                ];
            })
            ->all();

        $tramos = array_map(fn ($t) => [
            'tramo'    => $t['etiqueta'],
            'clientes' => count(array_filter($filas, fn ($x) => $x['tramo'] === $t['etiqueta'])),
            'total'    => array_sum(array_column(array_filter($filas, fn ($x) => $x['tramo'] === $t['etiqueta']), 'total')),
        ], self::TRAMOS_INACTIVIDAD);

        return [
            'filas'   => $filas,
            'totales' => [
                'clientes'      => count($filas),
                'compras'       => array_sum(array_column($filas, 'compras')),
                'total'         => array_sum(array_column($filas, 'total')),
                'con_email'     => count(array_filter($filas, fn ($x) => !empty($x['email']))),
                'con_telefono'  => count(array_filter($filas, fn ($x) => !empty($x['telefono']))),
                'dias_umbral'   => $dias,
                'tramos'        => $tramos,
            ],
        ];
    }

    private function tramo(int $dias): string
    {
        foreach (self::TRAMOS_INACTIVIDAD as $t) {
            if ($dias >= $t['min'] && ($t['max'] === null || $dias <= $t['max'])) {
                return $t['etiqueta'];
            }
        }

        return self::TRAMOS_INACTIVIDAD[array_key_last(self::TRAMOS_INACTIVIDAD)]['etiqueta'];
    }
}
