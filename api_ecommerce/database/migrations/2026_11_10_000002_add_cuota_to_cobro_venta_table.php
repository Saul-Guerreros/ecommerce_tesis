<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza el cobro con la cuota que salda. Espejo de pago_compra.pgo_cuoid.
 *
 * NULLABLE a proposito: un cobro al contado salda la factura entera y no
 * tiene cuota. Solo los cobros de una venta a credito lo llevan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobro_venta', function (Blueprint $table) {
            // bigint: cta_cobrar.cco_id es bigserial.
            $table->unsignedBigInteger('cob_ccoid')->nullable()->after('cob_venid');

            $table->foreign('cob_ccoid', 'fk_cob_cta_cobrar')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        // Sin pagos parciales, una cuota tiene como maximo UN cobro activo.
        // Anulado el cobro, la cuota vuelve a poder cobrarse. Mismo criterio
        // que uq_cob_ppa_activo.
        DB::statement('CREATE UNIQUE INDEX uq_cob_cuota_activo
            ON cobro_venta (cob_ccoid) WHERE cob_ccoid IS NOT NULL AND cob_estado = 1');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_cob_cuota_activo');

        Schema::table('cobro_venta', function (Blueprint $table) {
            $table->dropForeign('fk_cob_cta_cobrar');
            $table->dropColumn('cob_ccoid');
        });
    }
};