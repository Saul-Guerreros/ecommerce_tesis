<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula el kardex con la LÍNEA de repuesto de la OT que originó la salida
 * (mismo criterio que mst_cmdid -> compra_detalle, mst_dncid -> det_nota_credito
 * y mst_dpdid -> detalle_pedido). Un repuesto de servicio es salida comercial:
 * NO pasa por ajuste_inventario (regla 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('mst_osrid')->nullable()->after('mst_dpdid');

            $table->foreign('mst_osrid', 'fk_mst_osr')
                  ->references('osr_id')->on('orden_servicio_repuesto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('mst_osrid', 'ix_mst_osrid');
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->dropForeign('fk_mst_osr');
            $table->dropIndex('ix_mst_osrid');
            $table->dropColumn('mst_osrid');
        });
    }
};
