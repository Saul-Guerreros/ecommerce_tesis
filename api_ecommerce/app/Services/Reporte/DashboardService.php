<?php

namespace App\Services\Reporte;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Venta\VentaCab;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del dashboard del panel administrativo.
 *
 * Todas las agregaciones se resuelven en PostgreSQL (SUM, COUNT, GROUP BY,
 * FILTER, generate_series): PHP solo recibe filas ya resumidas.
 * Cada bloque se cachea por combinación de filtros (FiltrosReporte::claveCache).
 */
class DashboardService extends ConsultaReporte
{
    private const TTL        = 300; // 5 min: métricas agregadas
    private const TTL_TABLAS = 60;  // 1 min: listados operativos

    private const DIAS  = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    private const SERVICIO_CERRADO = ['ENTREGADA', 'CANCELADA'];

    public function __construct(private readonly VentasReporteService $ventas)
    {
    }

    // =====================================================================
    // 1. Widgets superiores
    // =====================================================================

    public function resumen(FiltrosReporte $f): array
    {
        return Cache::remember($f->claveCache('dash:resumen'), self::TTL, function () use ($f) {
            $actual   = $this->totalesFacturas($f);
            $anterior = $this->totalesFacturas($f->periodoAnterior());

            $ticketActual   = $actual['cantidad'] ? $actual['total'] / $actual['cantidad'] : 0;
            $ticketAnterior = $anterior['cantidad'] ? $anterior['total'] / $anterior['cantidad'] : 0;

            return [
                'ventas' => [
                    'total'     => $this->gs($actual['total']),
                    'anterior'  => $this->gs($anterior['total']),
                    'variacion' => $this->variacion($actual['total'], $anterior['total']),
                    'facturas'  => $actual['cantidad'],
                    'diario'    => $this->ventasDiarias($f),
                ],
                'pedidos'  => $this->widgetPedidos($f),
                'ticket'   => [
                    'promedio'  => $this->gs($ticketActual),
                    'anterior'  => $this->gs($ticketAnterior),
                    'variacion' => $this->variacion($ticketActual, $ticketAnterior),
                ],
                'servicio' => $this->widgetServicio($f),
            ];
        });
    }

    private function totalesFacturas(FiltrosReporte $f): array
    {
        $r = $this->facturasVigentes($f)
            ->selectRaw('COALESCE(SUM(vc.ven_total), 0) AS total, COUNT(*) AS cantidad')
            ->first();

        return ['total' => (float) $r->total, 'cantidad' => (int) $r->cantidad];
    }

    /** Serie diaria completa: generate_series rellena con 0 los días sin ventas. */
    private function ventasDiarias(FiltrosReporte $f): array
    {
        $sql = "SELECT d.dia::date AS fecha, COALESCE(SUM(vc.ven_total), 0) AS total
                  FROM generate_series(?::date, ?::date, interval '1 day') AS d(dia)
                  LEFT JOIN venta_cab vc
                         ON vc.ven_fchaem = d.dia::date
                        AND vc.ven_estado <> ?"
            . ($f->sucursalId ? ' AND vc.ven_sucid = ?' : '')
            . ' GROUP BY d.dia ORDER BY d.dia';

        $bindings = [...$f->rangoFechas(), VentaCab::ESTADO_ANULADA];
        if ($f->sucursalId) {
            $bindings[] = $f->sucursalId;
        }

        return array_map(fn ($r) => [
            'fecha' => $r->fecha,
            'total' => $this->gs($r->total),
        ], DB::select($sql, $bindings));
    }

    /** Pedidos creados en el período (sin cancelados) y cuántos aún no se concretaron. */
    private function widgetPedidos(FiltrosReporte $f): array
    {
        $q = DB::table('pedido as p')
            ->join('estado_pedido as ep', 'ep.est_id', '=', 'p.ped_estid')
            ->where('ep.est_codigo', '<>', EstadoPedido::CANCELADO)
            ->whereBetween('p.ped_creado', $f->rangoTimestamps());

        $this->filtrarSucursalPedido($q, $f);

        $r = $q->selectRaw(
            'COUNT(*) AS total,
             COUNT(*) FILTER (WHERE NOT EXISTS (
                 SELECT 1 FROM venta_cab v WHERE v.ven_pedid = p.ped_id AND v.ven_estado <> ?
             )) AS pendientes',
            [VentaCab::ESTADO_ANULADA]
        )->first();

        return ['total' => (int) $r->total, 'pendientes' => (int) $r->pendientes];
    }

    /** Órdenes con trabajo técnico sin cerrar (foto actual) y las iniciadas en el período. */
    private function widgetServicio(FiltrosReporte $f): array
    {
        $base = fn () => DB::table('orden_servicio as os')
            ->join('solicitud_servicio as ss', 'ss.sse_id', '=', 'os.ose_sseid');

        $abiertas = $base()
            ->join('estado_servicio as es', 'es.ets_id', '=', 'ss.sse_etsid')
            ->whereNull('os.ose_fecfin')
            ->whereNotIn('es.ets_codigo', self::SERVICIO_CERRADO);
        $this->filtrarSucursalNullable($abiertas, $f, 'ss.sse_sucid');

        $nuevas = $base()->whereBetween('os.ose_fecini', $f->rangoTimestamps());
        $this->filtrarSucursalNullable($nuevas, $f, 'ss.sse_sucid');

        return ['abiertas' => $abiertas->count(), 'nuevas' => $nuevas->count()];
    }

    // =====================================================================
    // 2. Gráfico principal (Ventas vs Compras) + totales del pie
    // =====================================================================

    public function ventasCompras(FiltrosReporte $f): array
    {
        return Cache::remember($f->claveCache('dash:vc'), self::TTL, fn () => [
            'serie'   => $this->serieVentasCompras($f),
            'totales' => $this->totalesPeriodo($f),
        ]);
    }

    private function serieVentasCompras(FiltrosReporte $f): array
    {
        // $u sale de una lista blanca: interpolarlo no permite inyección SQL.
        $u    = self::UNIDAD[$f->agrupacion] ?? 'month';
        $sucV = $f->sucursalId ? 'AND ven_sucid = ?' : '';
        $sucC = $f->sucursalId ? 'AND com_sucid = ?' : '';

        $sql = "
            WITH periodos AS (
                SELECT generate_series(date_trunc('{$u}', ?::date),
                                       date_trunc('{$u}', ?::date),
                                       interval '1 {$u}')::date AS periodo
            ),
            v AS (
                SELECT date_trunc('{$u}', ven_fchaem)::date AS periodo, SUM(ven_total) AS total
                  FROM venta_cab
                 WHERE ven_estado <> ? AND ven_fchaem BETWEEN ? AND ? {$sucV}
                 GROUP BY 1
            ),
            c AS (
                SELECT date_trunc('{$u}', com_fchaem)::date AS periodo, SUM(com_total) AS total
                  FROM compra_cab
                 WHERE com_estfac = 1 AND com_fchaem BETWEEN ? AND ? {$sucC}
                 GROUP BY 1
            )
            SELECT p.periodo, COALESCE(v.total, 0) AS ventas, COALESCE(c.total, 0) AS compras
              FROM periodos p
              LEFT JOIN v USING (periodo)
              LEFT JOIN c USING (periodo)
             ORDER BY p.periodo";

        [$desde, $hasta] = $f->rangoFechas();
        $suc = $f->sucursalId ? [$f->sucursalId] : [];

        $bindings = [
            $desde, $hasta,
            VentaCab::ESTADO_ANULADA, $desde, $hasta, ...$suc,
            $desde, $hasta, ...$suc,
        ];

        return array_map(fn ($r) => [
            'periodo'  => $r->periodo,
            'etiqueta' => $this->etiquetaPeriodo($r->periodo, $f->agrupacion),
            'ventas'   => $this->gs($r->ventas),
            'compras'  => $this->gs($r->compras),
        ], DB::select($sql, $bindings));
    }

    private function totalesPeriodo(FiltrosReporte $f): array
    {
        $ventas  = (float) $this->facturasVigentes($f)->sum('vc.ven_total');
        $compras = (float) $this->comprasConfirmadas($f)->sum('cc.com_total');

        // Margen sobre valores SIN IVA: el CPP (vnd_cosuni) ya es sin IVA.
        $m = $this->lineasVendidas($f)->selectRaw(
            'COALESCE(SUM(vd.vnd_valorventa - vd.vnd_iva), 0)                AS venta_neta,
             COALESCE(SUM(vd.vnd_cantidad * COALESCE(vd.vnd_cosuni, 0)), 0) AS costo,
             COALESCE(SUM(vd.vnd_cantidad), 0)                              AS unidades'
        )->first();

        $ventaNeta = (float) $m->venta_neta;
        $margen    = $ventaNeta - (float) $m->costo;

        return [
            'ventas'          => $this->gs($ventas),
            'compras'         => $this->gs($compras),
            'margen_bruto'    => $this->gs($margen),
            'margen_pct'      => $ventaNeta > 0 ? round($margen / $ventaNeta * 100, 1) : null,
            'clientes_nuevos' => $this->facturasVigentes($f)
                ->joinSub($this->primerasCompras(), 'pc', 'pc.ven_id', '=', 'vc.ven_id')
                ->count(),
            'unidades'        => (int) round((float) $m->unidades),
        ];
    }

    // =====================================================================
    // 3. Bloque secundario (distribuciones)
    // =====================================================================

    public function distribucion(FiltrosReporte $f): array
    {
        return Cache::remember($f->claveCache('dash:dist'), self::TTL, fn () => [
            'dia_semana'    => $this->ventasPorDiaSemana($f),
            'categorias'    => array_map(fn ($c) => [
                'id'        => $c['id'],
                'categoria' => $c['nombre'],
                'total'     => $c['total'],
                'unidades'  => (int) round($c['unidades']),
            ], $this->ventas->agrupado($f, 'categoria', $f->limite)['filas']),
            'metodos_pago'  => array_map(
                fn ($m) => array_diff_key($m, ['participacion' => 0]),
                $this->ventas->porMetodoPago($f)['filas']
            ),
            'departamentos' => $this->ventas->porUbicacion($f, conCiudad: false)['filas'],
            'ciudades'      => $this->ventas->porUbicacion($f, conCiudad: true, limite: $f->limite)['filas'],
        ]);
    }

    /** Nuevo = la factura es la primera compra del cliente; recurrente = ya compró antes. */
    private function ventasPorDiaSemana(FiltrosReporte $f): array
    {
        $filas = $this->facturasVigentes($f)
            ->leftJoinSub($this->primerasCompras(), 'pc', 'pc.ven_id', '=', 'vc.ven_id')
            ->selectRaw(
                'EXTRACT(ISODOW FROM vc.ven_fchaem)::int                              AS dia,
                 COALESCE(SUM(vc.ven_total) FILTER (WHERE pc.ven_id IS NOT NULL), 0) AS nuevos,
                 COALESCE(SUM(vc.ven_total) FILTER (WHERE pc.ven_id IS NULL), 0)     AS recurrentes'
            )
            ->groupByRaw('1')
            ->get()
            ->keyBy('dia');

        // Siempre los 7 días, aunque alguno no tenga ventas.
        return array_map(fn (int $dia) => [
            'dia'         => $dia,
            'etiqueta'    => self::DIAS[$dia],
            'nuevos'      => $this->gs($filas[$dia]->nuevos ?? 0),
            'recurrentes' => $this->gs($filas[$dia]->recurrentes ?? 0),
        ], array_keys(self::DIAS));
    }

    

    /**
     * Pagos registrados (no rechazados) de pedidos facturados. Soporta pago
     * dividido. Si un pedido no tiene pagos registrados (ej. contra entrega),
     * se usa el método elegido en el pedido por el total de la factura.
     */

    /**
     * Envío: departamento/ciudad del snapshot de pedido_entrega.
     * Retiro: ciudad de la sucursal de retiro (pen_sucid).
     */
   

    // =====================================================================
    // 4. Tablas operativas
    // =====================================================================

    public function tablas(FiltrosReporte $f): array
    {
        return Cache::remember($f->claveCache('dash:tablas'), self::TTL_TABLAS, fn () => [
            'ultimos_pedidos' => $this->ultimosPedidos($f),
            'stock_bajo'      => $this->stockBajoMinimo($f),
        ]);
    }

    private function ultimosPedidos(FiltrosReporte $f): array
    {
        $q = DB::table('pedido as p')
            ->join('estado_pedido as ep', 'ep.est_id', '=', 'p.ped_estid')
            ->leftJoin('estado_pago as ea', 'ea.epa_id', '=', 'p.ped_epaid')
            ->leftJoin('pedido_entrega as pe', 'pe.pen_pedid', '=', 'p.ped_id');

        $this->filtrarSucursalPedido($q, $f);

        return $q->orderByDesc('p.ped_creado')
            ->limit($f->limite)
            ->get([
                'p.ped_id', 'p.ped_numero', 'p.ped_total', 'p.ped_creado',
                DB::raw("CONCAT_WS(' ', pe.pen_nombre, pe.pen_apelli) AS cliente"),
                'ep.est_codigo', 'ep.est_descri', 'ea.epa_codigo', 'ea.epa_descri',
            ])
            ->map(fn ($r) => [
                'id'            => (int) $r->ped_id,
                'numero'        => $r->ped_numero,
                'cliente'       => $r->cliente ?: '—',
                'total'         => $this->gs($r->ped_total),
                'fecha'         => $r->ped_creado,
                'estado'        => ['codigo' => $r->est_codigo, 'descripcion' => $r->est_descri],
                'estado_pago'   => ['codigo' => $r->epa_codigo, 'descripcion' => $r->epa_descri],
            ])
            ->all();
    }

    /** Disponible (existencia − reservado) por debajo del stock mínimo del producto. */
    private function stockBajoMinimo(FiltrosReporte $f): array
    {
        $q = DB::table('stock as st')
            ->join('producto as pr', 'pr.prd_id', '=', 'st.sto_prdid')
            ->join('deposito as d', 'd.dpo_id', '=', 'st.sto_depid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_afestk', 1)
            ->where('pr.prd_stkmin', '>', 0)
            ->whereRaw('(st.sto_cantid - st.sto_reserv) < pr.prd_stkmin')
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('d.dpo_sucid', $id))
            ->when($f->depositoId, fn (Builder $q, int $id) => $q->where('st.sto_depid', $id));

        $total = (clone $q)->count();

        $items = $q->orderByRaw('(st.sto_cantid - st.sto_reserv) / pr.prd_stkmin')
            ->limit($f->limite)
            ->get([
                'pr.prd_id', 'pr.prd_codinv', 'pr.prd_stkmin', 'd.dpo_nombre',
                DB::raw('COALESCE(pw.pub_titulo, LEFT(pr.prd_descri, 120)) AS producto'),
                DB::raw('(st.sto_cantid - st.sto_reserv) AS disponible'),
            ])
            ->map(fn ($r) => [
                'id'         => (int) $r->prd_id,
                'codigo'     => $r->prd_codinv,
                'producto'   => $r->producto,
                'deposito'   => $r->dpo_nombre,
                'disponible' => (float) $r->disponible,
                'minimo'     => (int) $r->prd_stkmin,
                'faltante'   => (float) ($r->prd_stkmin - $r->disponible),
            ])
            ->all();

        return ['total' => $total, 'items' => $items];
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /** Primera factura vigente de cada cliente (MIN(ven_id): los ids crecen con el tiempo). */
    private function primerasCompras(): Builder
    {
        return DB::table('venta_cab')
            ->where('ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('ven_cliid')
            ->groupBy('ven_cliid')
            ->selectRaw('MIN(ven_id) AS ven_id');
    }

    /** Igual que filtrarSucursalPedido, para columnas de sucursal nullable (servicio técnico). */
    private function filtrarSucursalNullable(Builder $q, FiltrosReporte $f, string $columna): void
    {
        if (!$f->sucursalId) {
            return;
        }

        if ($f->sucursalId === $this->sucursalPrincipalId()) {
            $q->where(fn (Builder $w) => $w->where($columna, $f->sucursalId)->orWhereNull($columna));
            return;
        }

        $q->where($columna, $f->sucursalId);
    }

}