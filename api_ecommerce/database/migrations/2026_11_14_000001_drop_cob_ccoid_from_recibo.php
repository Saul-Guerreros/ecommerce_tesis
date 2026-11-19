<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita cob_ccoid de recibo.
 *
 * Venia de 2026_11_10_000002_add_cuota_to_cobro_venta_table, que ataba
 * el cobro a UNA cuota desde la cabecera. Ese vinculo se mudo a
 * recibo_detalle para permitir cobrar varias cuotas en un solo recibo.
 * La columna quedo huerfana: el renombre a recibo no la contemplaba, asi
 * que conserva su nombre viejo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('recibo', 'cob_ccoid')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS uq_cob_cuota_activo');

        Schema::table('recibo', function (Blueprint $table) {
            $table->dropForeign('fk_cob_cta_cobrar');
            $table->dropColumn('cob_ccoid');
        });
    }

    public function down(): void
    {
        Schema::table('recibo', function (Blueprint $table) {
            $table->unsignedBigInteger('cob_ccoid')->nullable();

            $table->foreign('cob_ccoid', 'fk_cob_cta_cobrar')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
    }
};