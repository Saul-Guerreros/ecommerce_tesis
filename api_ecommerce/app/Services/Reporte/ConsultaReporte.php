<?php

namespace App\Services\Reporte;

use App\Models\Venta\VentaCab;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Base de los Services del módulo (Dashboard, Ventas, Inventario...).
 *
 * Centraliza las reglas de negocio que comparten todas las métricas:
 *   - Venta concretada = factura vigente (ven_estado <> ANULADA).
 *   - Pedidos de envío (ped_sucid NULL) se atribuyen a la sucursal principal.
 *   - Agrupación temporal con lista blanca (evita inyección SQL en date_trunc).
 *
 * Usa Query Builder y no Eloquent: los reportes devuelven agregados,
 * no entidades, así que no hace falta hidratar modelos.
 */
abstract class ConsultaReporte
{
    /** Línea de producto (excluye ENVIO, que no tiene vnd_prdid). */
    protected const SOLO_PRODUCTOS = 'vd.vnd_prdid IS NOT NULL';

    protected const UNIDAD = ['dia' => 'day', 'mes' => 'month', 'anio' => 'year'];

    protected const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** Título de la publicación web o, si no tiene, la descripción del producto. Alias: pw, pr. */
    protected const NOMBRE_PRODUCTO = 'COALESCE(pw.pub_titulo, LEFT(pr.prd_descri, 120))';

    /** Categoría propia o, en variantes, la del producto padre. Alias: pw, pwp. */
    protected const CATEGORIA_PRODUCTO = 'COALESCE(pw.pub_catid, pwp.pub_catid)';

    private ?int $sucursalPrincipal = null;

    // =====================================================================
    // Consultas base
    // =====================================================================

    /** Facturas vigentes del período, filtradas por sucursal. Alias: vc. */
    protected function facturasVigentes(FiltrosReporte $f): Builder
    {
        return DB::table('venta_cab as vc')
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereBetween('vc.ven_fchaem', $f->rangoFechas())
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('vc.ven_sucid', $id));
    }

    /** Líneas de producto de facturas vigentes. Alias: vc, vd. */
    protected function lineasVendidas(FiltrosReporte $f): Builder
    {
        return $this->facturasVigentes($f)
            ->join('venta_detalle as vd', 'vd.vnd_venid', '=', 'vc.ven_id')
            ->whereRaw(self::SOLO_PRODUCTOS);
    }

    /**
     * Líneas vendidas con producto, publicación propia y del padre. Alias: vc, vd, pr, pw, pwp.
     * pub_prdid es UNIQUE (1:1), así que los LEFT JOIN no duplican filas.
     */
    protected function lineasConProducto(FiltrosReporte $f): Builder
    {
        return $this->lineasVendidas($f)
            ->join('producto as pr', 'pr.prd_id', '=', 'vd.vnd_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->leftJoin('publicacion_web as pwp', 'pwp.pub_prdid', '=', 'pr.prd_padre_id');
    }

    /** Compras confirmadas del período, filtradas por sucursal. Alias: cc. */
    protected function comprasConfirmadas(FiltrosReporte $f): Builder
    {
        return DB::table('compra_cab as cc')
            ->where('cc.com_estfac', 1)
            ->whereBetween('cc.com_fchaem', $f->rangoFechas())
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('cc.com_sucid', $id));
    }

    /** Filtro de sucursal sobre pedidos: los envíos (ped_sucid NULL) son de la principal. */
    protected function filtrarSucursalPedido(Builder $query, FiltrosReporte $f, string $alias = 'p'): Builder
    {
        if (!$f->sucursalId) {
            return $query;
        }

        if ($f->sucursalId === $this->sucursalPrincipalId()) {
            return $query->where(fn (Builder $q) => $q
                ->where("{$alias}.ped_sucid", $f->sucursalId)
                ->orWhereNull("{$alias}.ped_sucid"));
        }

        return $query->where("{$alias}.ped_sucid", $f->sucursalId);
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /** Expresión SQL de agrupación temporal ("2026-10-01", etc.). */
    protected function periodoSql(string $columna, string $agrupacion): string
    {
        $unidad = self::UNIDAD[$agrupacion] ?? 'month';

        return "date_trunc('{$unidad}', {$columna})::date";
    }

    protected function etiquetaPeriodo(string $fecha, string $agrupacion): string
    {
        [$anio, $mes, $dia] = explode('-', substr($fecha, 0, 10));

        return match ($agrupacion) {
            'dia'   => "{$dia}/{$mes}/{$anio}",
            'anio'  => $anio,
            default => self::MESES[(int) $mes - 1] . ' ' . $anio,
        };
    }

    /** IDs de una categoría y todas sus subcategorías (árbol cat_idpadr). */
    protected function categoriaConDescendientes(int $categoriaId): array
    {
        $filas = DB::select('
            WITH RECURSIVE arbol AS (
                SELECT cat_id FROM categoria WHERE cat_id = ?
                UNION ALL
                SELECT c.cat_id FROM categoria c JOIN arbol a ON c.cat_idpadr = a.cat_id
            )
            SELECT cat_id FROM arbol', [$categoriaId]);

        return array_map(fn ($r) => (int) $r->cat_id, $filas);
    }

    protected function sucursalPrincipalId(): ?int
    {
        return $this->sucursalPrincipal ??= DB::table('sucursal')
            ->where('suc_princi', 1)
            ->where('suc_estado', 1)
            ->value('suc_id');
    }

    /** Variación porcentual; null si el período anterior es 0 (división indefinida). */
    protected function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior == 0.0) {
            return null;
        }

        return round(($actual - $anterior) / $anterior * 100, 1);
    }

    /** Porcentaje de una parte sobre un total; null si el total es 0. */
    protected function porcentaje(float $parte, float $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    /** Guaraníes: sin decimales. */
    protected function gs(mixed $valor): int
    {
        return (int) round((float) $valor);
    }
}