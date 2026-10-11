<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula el kardex con la linea de pedido que lo origino.
 *
 * movimiento_stock ya tenia mst_cmdid (compra_detalle) y mst_dncid
 * (nota de credito). Falta la salida por venta online: sin esta columna,
 * el kardex mostraria una salida de stock sin poder decir de que pedido
 * vino, y el modulo Movimiento de Stock no podria clasificar el origen.
 *
 * Nullable: los movimientos existentes (compras y ajustes) no tienen
 * pedido asociado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('mst_dpdid')->nullable()->after('mst_dncid');

            $table->foreign('mst_dpdid', 'fk_mst_dpd')
                  ->references('dpd_id')->on('detalle_pedido')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('mst_dpdid', 'ix_mst_dpdid');
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->dropForeign('fk_mst_dpd');
            $table->dropIndex('ix_mst_dpdid');
            $table->dropColumn('mst_dpdid');
        });
    }
};