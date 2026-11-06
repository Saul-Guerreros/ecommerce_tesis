<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * vnd_cosuni: costo promedio ponderado (sin IVA) de la unidad vendida,
 * congelado al emitir la factura. Insumo del margen bruto.
 *
 * NULL en lineas sin producto (ENVIO): no tienen costo de mercaderia.
 *
 * Backfill de facturas ya emitidas:
 *   1. Costo exacto del kardex (salida original del pedido, sin reversas).
 *   2. Si no hay movimiento, CPP actual del deposito principal de la
 *      sucursal emisora (aproximacion documentada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->decimal('vnd_cosuni', 14, 4)->nullable()->after('vnd_iva');
        });

        DB::statement("ALTER TABLE venta_detalle
            ADD CONSTRAINT ck_vnd_cosuni CHECK (vnd_cosuni IS NULL OR vnd_cosuni >= 0)");

        // 1. Costo exacto desde el kardex
        DB::statement("
            UPDATE venta_detalle vd
               SET vnd_cosuni = sub.costo
              FROM (
                    SELECT vd2.vnd_id, AVG(ms.mst_cosuni) AS costo
                      FROM venta_detalle vd2
                      JOIN venta_cab vc       ON vc.ven_id = vd2.vnd_venid
                      JOIN detalle_pedido dp  ON dp.dpd_pedid = vc.ven_pedid
                                             AND dp.dpd_prdid = vd2.vnd_prdid
                      JOIN movimiento_stock ms ON ms.mst_dpdid = dp.dpd_id
                                              AND ms.mst_tipo = 'S'
                                              AND ms.mst_revid IS NULL
                     WHERE vd2.vnd_prdid IS NOT NULL
                     GROUP BY vd2.vnd_id
                   ) sub
             WHERE vd.vnd_id = sub.vnd_id
        ");

        // 2. Respaldo: CPP actual del deposito principal de la sucursal emisora
        DB::statement("
            UPDATE venta_detalle vd
               SET vnd_cosuni = s.sto_cosprm
              FROM venta_cab vc, deposito d, stock s
             WHERE vc.ven_id     = vd.vnd_venid
               AND d.dpo_sucid   = vc.ven_sucid
               AND d.dpo_princi  = 1
               AND s.sto_depid   = d.dpo_id
               AND s.sto_prdid   = vd.vnd_prdid
               AND vd.vnd_prdid IS NOT NULL
               AND vd.vnd_cosuni IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE venta_detalle DROP CONSTRAINT IF EXISTS ck_vnd_cosuni');

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->dropColumn('vnd_cosuni');
        });
    }
};