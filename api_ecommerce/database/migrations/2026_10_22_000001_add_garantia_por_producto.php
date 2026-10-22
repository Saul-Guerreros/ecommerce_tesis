<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garantía por producto.
 *
 * producto.prd_gardia          Días de garantía del producto.
 *                              NULL = usa el plazo general (config servicio.garantia_compra_dias).
 *                              0    = sin garantía.
 * detalle_pedido.dpd_gardia    Snapshot del plazo al momento de la venta: si el admin cambia
 *                              después el plazo del producto, las compras ya hechas no se alteran.
 * pedido.ped_entreg            Fecha real de entrega: la garantía corre desde acá, no desde
 *                              la creación del pedido.
 * solicitud_servicio.sse_dpdid Línea de compra (producto) que cubre la garantía solicitada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->smallInteger('prd_gardia')->nullable();
        });
        DB::statement('ALTER TABLE producto ADD CONSTRAINT ck_prd_gardia
            CHECK (prd_gardia IS NULL OR prd_gardia BETWEEN 0 AND 3650)');

        Schema::table('detalle_pedido', function (Blueprint $table) {
            $table->smallInteger('dpd_gardia')->default(0);
        });
        DB::statement('ALTER TABLE detalle_pedido ADD CONSTRAINT ck_dpd_gardia
            CHECK (dpd_gardia BETWEEN 0 AND 3650)');

        // Backfill: las compras existentes conservan la cobertura que tenían (plazo general).
        DB::table('detalle_pedido')->update([
            'dpd_gardia' => (int) config('servicio.garantia_compra_dias', 365),
        ]);

        Schema::table('pedido', function (Blueprint $table) {
            $table->timestamp('ped_entreg')->nullable();
        });

        // Backfill: fecha en que cada pedido ya entregado pasó a ENTREGADO
        // (si no hay registro en el historial, se usa la última actualización o la creación).
        DB::statement("
            UPDATE pedido p
               SET ped_entreg = COALESCE(
                   (SELECT MAX(h.peh_fecha)
                      FROM pedido_historial_estado h
                      JOIN estado_pedido e ON e.est_id = h.peh_estid
                     WHERE h.peh_pedid = p.ped_id
                       AND h.peh_tipo  = 'PEDIDO'
                       AND e.est_codigo = 'ENTREGADO'),
                   p.ped_actual,
                   p.ped_creado)
             WHERE p.ped_estid = (SELECT est_id FROM estado_pedido WHERE est_codigo = 'ENTREGADO')
        ");

        Schema::table('solicitud_servicio', function (Blueprint $table) {
            $table->unsignedBigInteger('sse_dpdid')->nullable();
            $table->foreign('sse_dpdid', 'fk_sse_dpd')->references('dpd_id')->on('detalle_pedido')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_servicio', function (Blueprint $table) {
            $table->dropForeign('fk_sse_dpd');
            $table->dropColumn('sse_dpdid');
        });

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropColumn('ped_entreg');
        });

        DB::statement('ALTER TABLE detalle_pedido DROP CONSTRAINT IF EXISTS ck_dpd_gardia');
        Schema::table('detalle_pedido', function (Blueprint $table) {
            $table->dropColumn('dpd_gardia');
        });

        DB::statement('ALTER TABLE producto DROP CONSTRAINT IF EXISTS ck_prd_gardia');
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('prd_gardia');
        });
    }
};
