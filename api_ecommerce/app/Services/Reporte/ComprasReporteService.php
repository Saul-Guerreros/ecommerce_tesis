<?php

namespace App\Services\Reporte;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de Compras. Cada método devuelve ['filas' => [...], 'totales' => [...]]
 * (mismo contrato que Ventas/Inventario: pantalla y exportación comparten datos).
 *
 * Base: compras confirmadas (com_estfac = 1). Las anuladas no suman.
 * Cuentas por pagar: cuotas pendientes (cuo_estado = 0) de compras confirmadas;
 * el saldo descuenta los pagos activos registrados (pago_compra.pgo_estado = 1).
 */
class ComprasReporteService extends ConsultaReporte
{
    /** Tramos de antigüedad de deuda vencida (días). */
    private const TRAMOS_VENCIDA = [
        ['etiqueta' => '1 a 30 días',   'min' => 1,  'max' => 30],
        ['etiqueta' => '31 a 60 días',  'min' => 31, 'max' => 60],
        ['etiqueta' => '61 a 90 días',  'min' => 61, 'max' => 90],
        ['etiqueta' => 'Más de 90 días', 'min' => 91, 'max' => null],
    ];

    /** Tramos de deuda por vencer (días que faltan). */
    private const TRAMOS_POR_VENCER = [
        ['etiqueta' => 'Vence hoy',       'min' => 0,  'max' => 0],
        ['etiqueta' => '1 a 7 días',      'min' => 1,  'max' => 7],
        ['etiqueta' => '8 a 15 días',     'min' => 8,  'max' => 15],
        ['etiqueta' => '16 a 30 días',    'min' => 16, 'max' => 30],
        ['etiqueta' => 'Más de 30 días',  'min' => 31, 'max' => null],
    ];

    // =====================================================================
    // 1. Compras por proveedor
    // =====================================================================

    public function porProveedor(FiltrosReporte $f, ?int $proveedorId = null): array
    {
        $filas = $this->comprasConfirmadas($f)
            ->join('proveedor as pv', 'pv.pro_id', '=', 'cc.com_proid')
            ->when($proveedorId, fn (Builder $q, int $id) => $q->where('cc.com_proid', $id))
            ->selectRaw('
                pv.pro_id, pv.pro_razon, pv.pro_ruc,
                COUNT(*)                                                    AS compras,
                COALESCE(SUM(cc.com_total), 0)                              AS total,
                COALESCE(SUM(cc.com_total) FILTER (WHERE cc.com_condic = 0), 0) AS contado,
                COALESCE(SUM(cc.com_total) FILTER (WHERE cc.com_condic = 1), 0) AS credito,
                COALESCE(SUM(cc.com_iva5 + cc.com_iva10), 0)                AS iva,
                MAX(cc.com_fchaem)                                          AS ultima_compra')
            ->groupBy('pv.pro_id', 'pv.pro_razon', 'pv.pro_ruc')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'id'            => (int) $r->pro_id,
                'proveedor'     => $r->pro_razon,
                'ruc'           => $r->pro_ruc,
                'compras'       => (int) $r->compras,
                'contado'       => $this->gs($r->contado),
                'credito'       => $this->gs($r->credito),
                'iva'           => $this->gs($r->iva),
                'total'         => $this->gs($r->total),
                'promedio'      => $r->compras > 0 ? $this->gs($r->total / $r->compras) : 0,
                'ultima_compra' => $r->ultima_compra,
            ])
            ->all();

        $total = array_sum(array_column($filas, 'total'));
        foreach ($filas as &$fila) {
            $fila['participacion'] = $this->porcentaje($fila['total'], $total);
        }
        unset($fila);

        return [
            'filas'   => $filas,
            'totales' => [
                'proveedores' => count($filas),
                'compras'     => array_sum(array_column($filas, 'compras')),
                'contado'     => array_sum(array_column($filas, 'contado')),
                'credito'     => array_sum(array_column($filas, 'credito')),
                'iva'         => array_sum(array_column($filas, 'iva')),
                'total'       => $total,
            ],
        ];
    }

    // =====================================================================
    // 2. Cuentas por pagar vencidas
    // =====================================================================

    /** Cuotas pendientes con vencimiento anterior a hoy (foto actual, sin rango de fechas). */
    public function cuentasVencidas(FiltrosReporte $f, ?int $proveedorId = null): array
    {
        $filas = $this->cuotasPendientes($f, $proveedorId)
            ->where('cu.cuo_venci', '<', now()->toDateString())
            ->orderBy('cu.cuo_venci')
            ->get()
            ->map(function ($r) {
                $fila = $this->filaCuota($r);
                $fila['dias_vencido'] = -1 * (int) $r->dias;
                $fila['tramo']        = $this->tramo($fila['dias_vencido'], self::TRAMOS_VENCIDA);

                return $fila;
            })
            ->all();

        return ['filas' => $filas, 'totales' => $this->totalesCuotas($filas, self::TRAMOS_VENCIDA)];
    }

    // =====================================================================
    // 3. Cuentas por pagar por vencer
    // =====================================================================

    /** Cuotas pendientes que vencen desde hoy hasta hoy + $dias (foto actual). */
    public function cuentasPorVencer(FiltrosReporte $f, int $dias = 30, ?int $proveedorId = null): array
    {
        $hoy = now()->toDateString();

        $filas = $this->cuotasPendientes($f, $proveedorId)
            ->whereBetween('cu.cuo_venci', [$hoy, now()->addDays($dias)->toDateString()])
            ->orderBy('cu.cuo_venci')
            ->get()
            ->map(function ($r) {
                $fila = $this->filaCuota($r);
                $fila['dias_para_vencer'] = (int) $r->dias;
                $fila['tramo']            = $this->tramo($fila['dias_para_vencer'], self::TRAMOS_POR_VENCER);

                return $fila;
            })
            ->all();

        $totales = $this->totalesCuotas($filas, self::TRAMOS_POR_VENCER);
        $totales['dias_umbral'] = $dias;

        return ['filas' => $filas, 'totales' => $totales];
    }

    // =====================================================================
    // Base y auxiliares
    // =====================================================================

    /**
     * Cuotas pendientes de compras confirmadas, con proveedor y saldo.
     * (cuo_venci - CURRENT_DATE) en PostgreSQL devuelve días enteros:
     * negativo = vencida, positivo = por vencer.
     */
    private function cuotasPendientes(FiltrosReporte $f, ?int $proveedorId): Builder
    {
        $pagos = DB::table('pago_compra')
            ->where('pgo_estado', 1)
            ->groupBy('pgo_cuoid')
            ->selectRaw('pgo_cuoid, SUM(pgo_importe) AS pagado');

        return DB::table('cuota_compra as cu')
            ->join('compra_cab as cc', 'cc.com_id', '=', 'cu.cuo_comid')
            ->join('proveedor as pv', 'pv.pro_id', '=', 'cc.com_proid')
            ->leftJoinSub($pagos, 'pg', 'pg.pgo_cuoid', '=', 'cu.cuo_id')
            ->where('cu.cuo_estado', 0)
            ->where('cc.com_estfac', 1)
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('cc.com_sucid', $id))
            ->when($proveedorId, fn (Builder $q, int $id) => $q->where('cc.com_proid', $id))
            ->select([
                'cu.cuo_id', 'cu.cuo_nrocuo', 'cu.cuo_venci', 'cu.cuo_importe',
                'cc.com_id', 'cc.com_nrodoc', 'cc.com_fchaem', 'cc.com_cuotas',
                'pv.pro_id', 'pv.pro_razon', 'pv.pro_ruc',
                DB::raw('COALESCE(pg.pagado, 0) AS pagado'),
                DB::raw('(cu.cuo_venci - CURRENT_DATE) AS dias'),
            ]);
    }

    private function filaCuota(object $r): array
    {
        $importe = (float) $r->cuo_importe;
        $pagado  = (float) $r->pagado;

        return [
            'id'          => (int) $r->cuo_id,
            'proveedor'   => $r->pro_razon,
            'ruc'         => $r->pro_ruc,
            'factura'     => $r->com_nrodoc,
            'emision'     => $r->com_fchaem,
            'cuota'       => $r->cuo_nrocuo . '/' . ($r->com_cuotas ?? $r->cuo_nrocuo),
            'vencimiento' => $r->cuo_venci,
            'importe'     => $this->gs($importe),
            'pagado'      => $this->gs($pagado),
            'saldo'       => $this->gs(max(0, $importe - $pagado)),
        ];
    }

    private function totalesCuotas(array $filas, array $definicionTramos): array
    {
        $saldoTotal = array_sum(array_column($filas, 'saldo'));

        $tramos = array_map(function ($t) use ($filas, $saldoTotal) {
            $delTramo = array_filter($filas, fn ($f) => $f['tramo'] === $t['etiqueta']);
            $saldo    = array_sum(array_column($delTramo, 'saldo'));

            return [
                'tramo'         => $t['etiqueta'],
                'cuotas'        => count($delTramo),
                'saldo'         => $saldo,
                'participacion' => $this->porcentaje($saldo, $saldoTotal),
            ];
        }, $definicionTramos);

        return [
            'cuotas'      => count($filas),
            'proveedores' => count(array_unique(array_column($filas, 'proveedor'))),
            'importe'     => array_sum(array_column($filas, 'importe')),
            'pagado'      => array_sum(array_column($filas, 'pagado')),
            'saldo'       => $saldoTotal,
            'tramos'      => $tramos,
        ];
    }

    private function tramo(int $dias, array $definicionTramos): string
    {
        foreach ($definicionTramos as $t) {
            if ($dias >= $t['min'] && ($t['max'] === null || $dias <= $t['max'])) {
                return $t['etiqueta'];
            }
        }

        return end($definicionTramos)['etiqueta'];
    }
}
