<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo patrón que mst_cmdid (compra), mst_dncid (NC de compra),
 * mst_dpdid (pedido) y mst_osrid (repuesto de OT): la columna dice de qué
 * línea de qué documento vino el movimiento.
 *
 * Sin esto, el reingreso por devolución aparecería en el Kardex como una
 * entrada huérfana que el módulo Movimiento de Stock no sabría clasificar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->unsignedInteger('mst_dnvid')->nullable()->after('mst_osrid');

            $table->foreign('mst_dnvid', 'fk_mst_dnv')
                  ->references('dnv_id')->on('det_nota_credito_venta')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('mst_dnvid', 'ix_mst_dnvid');
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->dropForeign('fk_mst_dnv');
            $table->dropIndex('ix_mst_dnvid');
            $table->dropColumn('mst_dnvid');
        });
    }
};