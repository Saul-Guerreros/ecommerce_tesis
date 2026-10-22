<?php

namespace App\Services\Reporte;

use App\Models\Pedido\EstadoPago;
use App\Models\Venta\VentaCab;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de Ventas. Cada método devuelve ['filas' => [...], 'totales' => [...]]
 * para que la misma consulta sirva a la pantalla, al dashboard y a la
 * exportación PDF/Excel (Paso 7).
 *
 * Base: facturas vigentes = pedidos pagados (regla de negocio del módulo).
 */
class VentasReporteService extends ConsultaReporte
{
    // =====================================================================
    // 1. Por período
    // =====================================================================

    public function porPeriodo(FiltrosReporte $f): array
    {
        $u   = self::UNIDAD[$f->agrupacion] ?? 'month'; // lista blanca
        $suc = $f->sucursalId ? 'AND ven_sucid = ?' : '';

        $sql = "
            WITH periodos AS (
                SELECT generate_series(date_trunc('{$u}', ?::date),
                                       date_trunc('{$u}', ?::date),
                                       interval '1 {$u}')::date AS periodo
            ),
            v AS (
                SELECT date_trunc('{$u}', ven_fchaem)::date AS periodo,
                       COUNT(*) AS facturas, SUM(ven_total) AS total
                  FROM venta_cab
                 WHERE ven_estado <> ? AND ven_fchaem BETWEEN ? AND ? {$suc}
                 GROUP BY 1
            )
            SELECT p.periodo, COALESCE(v.facturas, 0) AS facturas, COALESCE(v.total, 0) AS total
              FROM periodos p LEFT JOIN v USING (periodo)
             ORDER BY p.periodo";

        [$desde, $hasta] = $f->rangoFechas();
        $bindings = [$desde, $hasta, VentaCab::ESTADO_ANULADA, $desde, $hasta];
        if ($f->sucursalId) {
            $bindings[] = $f->sucursalId;
        }

        $filas = array_map(fn ($r) => [
            'periodo'  => $r->periodo,
            'etiqueta' => $this->etiquetaPeriodo($r->periodo, $f->agrupacion),
            'facturas' => (int) $r->facturas,
            'total'    => $this->gs($r->total),
            'ticket'   => $r->facturas > 0 ? $this->gs($r->total / $r->facturas) : 0,
        ], DB::select($sql, $bindings));

        $total    = array_sum(array_column($filas, 'total'));
        $facturas = array_sum(array_column($filas, 'facturas'));

        return [
            'filas'   => $filas,
            'totales' => [
                'total'    => $total,
                'facturas' => $facturas,
                'ticket'   => $facturas > 0 ? $this->gs($total / $facturas) : 0,
            ],
        ];
    }

    // =====================================================================
    // 2. Por producto (con margen)
    // =====================================================================

    public function porProducto(FiltrosReporte $f, ?int $categoriaId = null, ?int $marcaId = null): array
    {
        $q = $this->lineasConProducto($f)
            ->leftJoin('categoria as ca', fn ($j) => $j->on(DB::raw('ca.cat_id'), '=', DB::raw(self::CATEGORIA_PRODUCTO)))
            ->leftJoin('marca as ma', 'ma.mar_id', '=', 'pr.prd_marid')
            ->when($categoriaId, fn (Builder $q) => $q->whereIn(
                DB::raw(self::CATEGORIA_PRODUCTO), $this->categoriaConDescendientes($categoriaId)))
            ->when($marcaId, fn (Builder $q, int $id) => $q->where('pr.prd_marid', $id))
            ->selectRaw('pr.prd_id, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . " AS producto,
                COALESCE(ca.cat_nombre, 'Sin categoría') AS categoria,
                COALESCE(ma.mar_descri, 'Sin marca')     AS marca")
            ->groupBy('pr.prd_id', 'pr.prd_codinv', 'pw.pub_titulo', 'pr.prd_descri', 'ca.cat_nombre', 'ma.mar_descri')
            ->orderByDesc('total');

        $this->seleccionarMetricasMargen($q);

        $filas = $q->get()->map(fn ($r) => [
            'id'        => (int) $r->prd_id,
            'codigo'    => $r->prd_codinv,
            'producto'  => $r->producto,
            'categoria' => $r->categoria,
            'marca'     => $r->marca,
            ...$this->metricasMargen($r),
        ])->all();

        return ['filas' => $filas, 'totales' => $this->totalesMargen($filas)];
    }

    // =====================================================================
    // 3. Por categoría o marca
    // =====================================================================

    /** @param string $por 'categoria' | 'marca' */
    public function agrupado(FiltrosReporte $f, string $por = 'categoria', ?int $limite = null): array
    {
        $q = $this->lineasConProducto($f);

        if ($por === 'marca') {
            $q->leftJoin('marca as ma', 'ma.mar_id', '=', 'pr.prd_marid')
                ->selectRaw("ma.mar_id AS id, COALESCE(ma.mar_descri, 'Sin marca') AS nombre")
                ->groupBy('ma.mar_id', 'ma.mar_descri');
        } else {
            $q->leftJoin('categoria as ca', fn ($j) => $j->on(DB::raw('ca.cat_id'), '=', DB::raw(self::CATEGORIA_PRODUCTO)))
                ->selectRaw("ca.cat_id AS id, COALESCE(ca.cat_nombre, 'Sin categoría') AS nombre")
                ->groupBy('ca.cat_id', 'ca.cat_nombre');
        }

        $this->seleccionarMetricasMargen($q);
        $q->orderByDesc('total')->when($limite, fn (Builder $q, int $l) => $q->limit($l));

        $filas = $q->get()->map(fn ($r) => [
            'id'     => $r->id ? (int) $r->id : null,
            'nombre' => $r->nombre,
            ...$this->metricasMargen($r),
        ])->all();

        $totales = $this->totalesMargen($filas);
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['total'], $totales['total']);
        }

        return ['filas' => $filas, 'totales' => $totales];
    }

    // =====================================================================
    // 4. Por método de pago
    // =====================================================================

    /**
     * Pagos registrados (no rechazados) de pedidos facturados; soporta pago
     * dividido. Pedidos sin pagos registrados (contra entrega) usan el
     * método elegido en el pedido por el total de la factura.
     */
    public function porMetodoPago(FiltrosReporte $f): array
    {
        $rechazado = DB::table('estado_pago')->where('epa_codigo', EstadoPago::RECHAZADO)->value('epa_id');

        $noRechazado = fn ($q) => $q->whereNull('pp.ppa_epaid')->orWhere('pp.ppa_epaid', '<>', $rechazado);

        $facturas = $this->facturasVigentes($f)
            ->whereNotNull('vc.ven_pedid')
            ->select('vc.ven_pedid', 'vc.ven_total');

        $conPagos = DB::table('pedido_pago as pp')
            ->joinSub($facturas, 'fa', 'fa.ven_pedid', '=', 'pp.ppa_pedid')
            ->where($noRechazado)
            ->select('pp.ppa_mpaid as mpaid', 'pp.ppa_monto as monto');

        $sinPagos = DB::table('pedido as p')
            ->joinSub($facturas, 'fa', 'fa.ven_pedid', '=', 'p.ped_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('pedido_pago as pp')
                ->whereColumn('pp.ppa_pedid', 'p.ped_id')
                ->where($noRechazado))
            ->select('p.ped_mpaid as mpaid', 'fa.ven_total as monto');

        $filas = DB::query()
            ->fromSub($conPagos->unionAll($sinPagos), 'u')
            ->leftJoin('metodo_pago as mp', 'mp.mpa_id', '=', 'u.mpaid')
            ->selectRaw("COALESCE(mp.mpa_descri, 'Sin especificar') AS metodo, SUM(u.monto) AS total, COUNT(*) AS operaciones")
            ->groupBy('mp.mpa_descri')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'metodo'      => $r->metodo,
                'operaciones' => (int) $r->operaciones,
                'total'       => $this->gs($r->total),
            ])
            ->all();

        return $this->conParticipacion($filas, ['operaciones']);
    }

    // =====================================================================
    // 5. Por ubicación (departamento / ciudad)
    // =====================================================================

    /**
     * Envío: snapshot de pedido_entrega. Retiro: ciudad de la sucursal (pen_sucid).
     */
    public function porUbicacion(FiltrosReporte $f, bool $conCiudad = true, ?int $limite = null): array
    {
        $departamento = "COALESCE(pe.pen_departa, de.dep_descri, 'Sin dato')";
        $ciudad       = "COALESCE(pe.pen_ciudad, ci.ciu_descri, 'Sin dato')";

        $q = $this->facturasVigentes($f)
            ->join('pedido_entrega as pe', 'pe.pen_pedid', '=', 'vc.ven_pedid')
            ->leftJoin('sucursal as s', 's.suc_id', '=', 'pe.pen_sucid')
            ->leftJoin('ciudad as ci', 'ci.ciu_id', '=', 's.suc_ciuid')
            ->leftJoin('departamento as de', 'de.dep_id', '=', 'ci.ciu_depid')
            ->selectRaw("{$departamento} AS departamento, COUNT(*) AS pedidos, SUM(vc.ven_total) AS total")
            ->groupByRaw($departamento);

        if ($conCiudad) {
            $q->selectRaw("{$ciudad} AS ciudad")->groupByRaw($ciudad);
        }

        $filas = $q->orderByDesc('total')
            ->when($limite, fn (Builder $q, int $l) => $q->limit($l))
            ->get()
            ->map(fn ($r) => array_filter([
                'departamento' => $r->departamento,
                'ciudad'       => $r->ciudad ?? null,
                'pedidos'      => (int) $r->pedidos,
                'total'        => $this->gs($r->total),
            ], fn ($v) => $v !== null))
            ->all();

        return $this->conParticipacion($filas, ['pedidos']);
    }

    // =====================================================================
    // Auxiliares de margen y totales
    // =====================================================================

    /** Métricas de venta y margen sobre líneas de producto (venta neta = sin IVA). */
    private function seleccionarMetricasMargen(Builder $q): void
    {
        $q->selectRaw('
            SUM(vd.vnd_cantidad)                                 AS unidades,
            SUM(vd.vnd_valorventa)                               AS total,
            SUM(vd.vnd_valorventa - vd.vnd_iva)                  AS venta_neta,
            SUM(vd.vnd_cantidad * COALESCE(vd.vnd_cosuni, 0))    AS costo');
    }

    private function metricasMargen(object $r): array
    {
        $ventaNeta = (float) $r->venta_neta;
        $margen    = $ventaNeta - (float) $r->costo;

        return [
            'unidades'   => round((float) $r->unidades, 2),
            'total'      => $this->gs($r->total),
            'venta_neta' => $this->gs($ventaNeta),
            'costo'      => $this->gs($r->costo),
            'margen'     => $this->gs($margen),
            'margen_pct' => $this->porcentaje($margen, $ventaNeta),
        ];
    }

    private function totalesMargen(array $filas): array
    {
        $ventaNeta = array_sum(array_column($filas, 'venta_neta'));
        $margen    = array_sum(array_column($filas, 'margen'));

        return [
            'unidades'   => round(array_sum(array_column($filas, 'unidades')), 2),
            'total'      => array_sum(array_column($filas, 'total')),
            'venta_neta' => $ventaNeta,
            'costo'      => array_sum(array_column($filas, 'costo')),
            'margen'     => $margen,
            'margen_pct' => $this->porcentaje($margen, $ventaNeta),
        ];
    }

    /** Agrega 'participacion' (% del total) a cada fila y calcula los totales. */
    private function conParticipacion(array $filas, array $sumables): array
    {
        $total   = array_sum(array_column($filas, 'total'));
        $totales = ['total' => $total];

        foreach ($sumables as $campo) {
            $totales[$campo] = array_sum(array_column($filas, $campo));
        }

        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['total'], $total);
        }

        return ['filas' => $filas, 'totales' => $totales];
    }
}