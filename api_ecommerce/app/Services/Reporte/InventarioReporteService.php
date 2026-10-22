<?php

namespace App\Services\Reporte;

use App\Models\ConfiguracionPrecio;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de Inventario. Fotos del estado actual del stock (sin rango de
 * fechas), filtrables por sucursal, depósito, categoría y marca.
 *
 * Valores a costo promedio ponderado (sto_cosprm, sin IVA), coherente con
 * el método de costeo definido en el sistema.
 */
class InventarioReporteService extends ConsultaReporte
{
    // =====================================================================
    // 1. Stock valorizado
    // =====================================================================

    public function valorizado(FiltrosReporte $f, ?int $categoriaId = null, ?int $marcaId = null): array
    {
        $base = fn () => $this->stockBase($f, $categoriaId, $marcaId)->where('st.sto_cantid', '>', 0);

        $filas = $base()
            ->selectRaw('pr.prd_id, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . " AS producto,
                COALESCE(ca.cat_nombre, 'Sin categoría') AS categoria,
                COALESCE(ma.mar_descri, 'Sin marca')     AS marca,
                d.dpo_nombre                             AS deposito,
                st.sto_cantid                            AS cantidad,
                st.sto_cosprm                            AS costo,
                st.sto_cantid * st.sto_cosprm            AS valor")
            ->orderByDesc('valor')
            ->get()
            ->map(fn ($r) => [
                'id'        => (int) $r->prd_id,
                'codigo'    => $r->prd_codinv,
                'producto'  => $r->producto,
                'categoria' => $r->categoria,
                'marca'     => $r->marca,
                'deposito'  => $r->deposito,
                'cantidad'  => round((float) $r->cantidad, 2),
                'costo'     => $this->gs($r->costo),
                'valor'     => $this->gs($r->valor),
            ])
            ->all();

        $porDeposito = $base()
            ->selectRaw('d.dpo_id AS id, d.dpo_nombre AS deposito,
                COUNT(*) AS productos, SUM(st.sto_cantid) AS unidades,
                SUM(st.sto_cantid * st.sto_cosprm) AS valor')
            ->groupBy('d.dpo_id', 'd.dpo_nombre')
            ->orderByDesc('valor')
            ->get();

        $valorTotal = (float) $porDeposito->sum('valor');

        return [
            'filas'   => $filas,
            'totales' => [
                'productos'    => count($filas),
                'unidades'     => round(array_sum(array_column($filas, 'cantidad')), 2),
                'valor'        => $this->gs($valorTotal),
                'por_deposito' => $porDeposito->map(fn ($d) => [
                    'id'            => (int) $d->id,
                    'deposito'      => $d->deposito,
                    'productos'     => (int) $d->productos,
                    'unidades'      => round((float) $d->unidades, 2),
                    'valor'         => $this->gs($d->valor),
                    'participacion' => $this->porcentaje((float) $d->valor, $valorTotal),
                ])->all(),
            ],
        ];
    }

    // =====================================================================
    // 2. Stock crítico
    // =====================================================================

    public function critico(FiltrosReporte $f, ?int $categoriaId = null, ?int $marcaId = null): array
    {
        $disponible = '(st.sto_cantid - st.sto_reserv)';

        $filas = $this->stockBase($f, $categoriaId, $marcaId)
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_stkmin', '>', 0)
            ->whereRaw("{$disponible} < pr.prd_stkmin")
            ->selectRaw('pr.prd_id, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . " AS producto,
                COALESCE(ca.cat_nombre, 'Sin categoría') AS categoria,
                COALESCE(ma.mar_descri, 'Sin marca')     AS marca,
                d.dpo_nombre                             AS deposito,
                st.sto_cantid                            AS existencia,
                st.sto_reserv                            AS reservado,
                {$disponible}                            AS disponible,
                pr.prd_stkmin                            AS minimo,
                st.sto_cosprm                            AS costo")
            ->orderByRaw("{$disponible} / pr.prd_stkmin")
            ->get()
            ->map(function ($r) {
                $disponible = (float) $r->disponible;
                $reponer    = max(0, (float) $r->minimo - $disponible);

                return [
                    'id'               => (int) $r->prd_id,
                    'codigo'           => $r->prd_codinv,
                    'producto'         => $r->producto,
                    'categoria'        => $r->categoria,
                    'marca'            => $r->marca,
                    'deposito'         => $r->deposito,
                    'existencia'       => round((float) $r->existencia, 2),
                    'reservado'        => round((float) $r->reservado, 2),
                    'disponible'       => round($disponible, 2),
                    'minimo'           => round((float) $r->minimo, 2),
                    'reponer'          => round($reponer, 2),
                    'costo_reposicion' => $this->gs($reponer * (float) $r->costo),
                    'nivel'            => $disponible <= 0 ? 'agotado' : 'critico',
                ];
            })
            ->all();

        $agotados = count(array_filter($filas, fn ($x) => $x['nivel'] === 'agotado'));

        return [
            'filas'   => $filas,
            'totales' => [
                'productos'        => count($filas),
                'agotados'         => $agotados,
                'criticos'         => count($filas) - $agotados,
                'costo_reposicion' => array_sum(array_column($filas, 'costo_reposicion')),
            ],
        ];
    }

    // =====================================================================
    // 3. Productos sin movimiento
    // =====================================================================

    /**
     * Salida = venta o servicio (mst_tipo 'S' que no proviene de un ajuste).
     * Los ajustes negativos (roturas, pérdidas) no cuentan como rotación.
     */
    public function sinMovimiento(FiltrosReporte $f, int $dias = 90, ?int $categoriaId = null, ?int $marcaId = null): array
    {
        $ultimos = DB::table('movimiento_stock')
            ->selectRaw("mst_stprid, mst_stdeid,
                MAX(mst_fecha) FILTER (WHERE mst_tipo = 'S' AND mst_aidid IS NULL AND mst_revid IS NULL) AS ultima_salida,
                MAX(mst_fecha) FILTER (WHERE mst_tipo = 'E') AS ultima_entrada")
            ->groupBy('mst_stprid', 'mst_stdeid');

        $limite = now()->subDays($dias)->toDateTimeString();

        $filas = $this->stockBase($f, $categoriaId, $marcaId)
            ->where('st.sto_cantid', '>', 0)
            ->leftJoinSub($ultimos, 'um', fn ($j) => $j
                ->on('um.mst_stprid', '=', 'st.sto_prdid')
                ->on('um.mst_stdeid', '=', 'st.sto_depid'))
            ->where(fn (Builder $q) => $q->whereNull('um.ultima_salida')->orWhere('um.ultima_salida', '<', $limite))
            ->selectRaw('pr.prd_id, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . " AS producto,
                COALESCE(ca.cat_nombre, 'Sin categoría') AS categoria,
                COALESCE(ma.mar_descri, 'Sin marca')     AS marca,
                d.dpo_nombre                             AS deposito,
                st.sto_cantid                            AS cantidad,
                st.sto_cantid * st.sto_cosprm            AS valor,
                um.ultima_salida, um.ultima_entrada,
                EXTRACT(DAY FROM now() - COALESCE(um.ultima_salida, um.ultima_entrada))::int AS dias")
            ->orderByDesc('valor')
            ->get()
            ->map(fn ($r) => [
                'id'             => (int) $r->prd_id,
                'codigo'         => $r->prd_codinv,
                'producto'       => $r->producto,
                'categoria'      => $r->categoria,
                'marca'          => $r->marca,
                'deposito'       => $r->deposito,
                'cantidad'       => round((float) $r->cantidad, 2),
                'valor'          => $this->gs($r->valor),
                'ultima_salida'  => $r->ultima_salida ? substr($r->ultima_salida, 0, 10) : null,
                'ultima_entrada' => $r->ultima_entrada ? substr($r->ultima_entrada, 0, 10) : null,
                'dias'           => $r->dias !== null ? (int) $r->dias : null,
            ])
            ->all();

        return [
            'filas'   => $filas,
            'totales' => [
                'productos'   => count($filas),
                'unidades'    => round(array_sum(array_column($filas, 'cantidad')), 2),
                'valor'       => array_sum(array_column($filas, 'valor')),
                'nunca'       => count(array_filter($filas, fn ($x) => $x['ultima_salida'] === null)),
                'dias_umbral' => $dias,
            ],
        ];
    }

    // =====================================================================
    // 4. Margen por producto (precio vigente vs CPP)
    // =====================================================================

    /**
     * Misma fórmula que PriceService: precio = costo × (1 + markup) × (1 + IVA).
     *   markup_real = precio_sin_iva / costo − 1
     *   margen %    = (precio_sin_iva − costo) / precio_sin_iva
     * Si |markup_real − markup_configurado| > 1 punto → precio desactualizado.
     *
     * Costo: CPP ponderado por existencia entre los depósitos filtrados.
     */
    public function margen(FiltrosReporte $f, ?int $categoriaId = null, ?int $marcaId = null): array
    {
        $markupGeneral = (float) (ConfiguracionPrecio::actual()->cpr_marmin ?? 0);

        $filas = $this->stockBase($f, $categoriaId, $marcaId)
            ->leftJoin('tipo_iva as ti', 'ti.tiv_id', '=', 'pr.prd_tivid')
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_habmin', 1)
            ->whereNotNull('pr.prd_pvtmin')
            ->selectRaw('pr.prd_id, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . " AS producto,
                COALESCE(ca.cat_nombre, 'Sin categoría') AS categoria,
                COALESCE(ma.mar_descri, 'Sin marca')     AS marca,
                pr.prd_pvtmin                            AS precio,
                COALESCE(ti.tiv_tasa, 0)                 AS tasa_iva,
                pr.prd_marmin                            AS markup_producto,
                SUM(st.sto_cantid)                       AS existencia,
                CASE WHEN SUM(st.sto_cantid) > 0
                     THEN SUM(st.sto_cantid * st.sto_cosprm) / SUM(st.sto_cantid)
                     ELSE MAX(st.sto_cosprm) END         AS costo")
            ->groupBy('pr.prd_id', 'pr.prd_codinv', 'pw.pub_titulo', 'pr.prd_descri', 'ca.cat_nombre',
                'ma.mar_descri', 'pr.prd_pvtmin', 'ti.tiv_tasa', 'pr.prd_marmin')
            ->havingRaw('MAX(st.sto_cosprm) > 0')
            ->get()
            ->map(function ($r) use ($markupGeneral) {
                $costo       = (float) $r->costo;
                $precioSinIva = (float) $r->precio / (1 + (float) $r->tasa_iva / 100);
                $ganancia    = $precioSinIva - $costo;
                $markupReal  = round(($precioSinIva / $costo - 1) * 100, 1);
                $markupConf  = (float) ($r->markup_producto ?? $markupGeneral);

                return [
                    'id'                => (int) $r->prd_id,
                    'codigo'            => $r->prd_codinv,
                    'producto'          => $r->producto,
                    'categoria'         => $r->categoria,
                    'marca'             => $r->marca,
                    'existencia'        => round((float) $r->existencia, 2),
                    'costo'             => $this->gs($costo),
                    'precio'            => $this->gs($r->precio),
                    'precio_sin_iva'    => $this->gs($precioSinIva),
                    'ganancia_unitaria' => $this->gs($ganancia),
                    'margen_pct'        => $this->porcentaje($ganancia, $precioSinIva),
                    'markup_real'       => $markupReal,
                    'markup_config'     => round($markupConf, 1),
                    'desactualizado'    => abs($markupReal - $markupConf) > 1,
                ];
            })
            ->sortBy('margen_pct')
            ->values()
            ->all();

        $margenes = array_filter(array_column($filas, 'margen_pct'), fn ($v) => $v !== null);

        return [
            'filas'   => $filas,
            'totales' => [
                'productos'       => count($filas),
                'margen_promedio' => $margenes ? round(array_sum($margenes) / count($margenes), 1) : null,
                'negativos'       => count(array_filter($filas, fn ($x) => $x['ganancia_unitaria'] < 0)),
                'desactualizados' => count(array_filter($filas, fn ($x) => $x['desactualizado'])),
                'markup_general'  => $markupGeneral,
            ],
        ];
    }

    // =====================================================================
    // Base
    // =====================================================================

    /**
     * Stock por producto y depósito, con nombre, categoría y marca.
     * Alias: st, pr, d, pw, pwp, ca, ma. Solo productos que controlan stock.
     */
    private function stockBase(FiltrosReporte $f, ?int $categoriaId, ?int $marcaId): Builder
    {
        return DB::table('stock as st')
            ->join('producto as pr', 'pr.prd_id', '=', 'st.sto_prdid')
            ->join('deposito as d', 'd.dpo_id', '=', 'st.sto_depid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->leftJoin('publicacion_web as pwp', 'pwp.pub_prdid', '=', 'pr.prd_padre_id')
            ->leftJoin('categoria as ca', fn ($j) => $j->on(DB::raw('ca.cat_id'), '=', DB::raw(self::CATEGORIA_PRODUCTO)))
            ->leftJoin('marca as ma', 'ma.mar_id', '=', 'pr.prd_marid')
            ->where('pr.prd_afestk', 1)
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('d.dpo_sucid', $id))
            ->when($f->depositoId, fn (Builder $q, int $id) => $q->where('st.sto_depid', $id))
            ->when($categoriaId, fn (Builder $q) => $q->whereIn(
                DB::raw(self::CATEGORIA_PRODUCTO), $this->categoriaConDescendientes($categoriaId)))
            ->when($marcaId, fn (Builder $q, int $id) => $q->where('pr.prd_marid', $id));
    }
}