<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cuota_pago pasa a cabecera/detalle, igual que recibo.
 *
 * El cliente declara UNA transferencia que puede cubrir varias cuotas
 * ("pago las cuotas 3 y 4 juntas"), asi que el vinculo con la cuota no
 * puede vivir en la cabecera. cpa_monto pasa a ser el total declarado.
 *
 * Se migran las filas existentes: cada cpa_ccoid se convierte en una
 * linea de detalle antes de borrar la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_pago_detalle', function (Blueprint $table) {
            $table->id('cpd_id');
            $table->unsignedBigInteger('cpd_cpaid');
            $table->unsignedBigInteger('cpd_ccoid');
            $table->decimal('cpd_monto', 14, 2);

            $table->foreign('cpd_cpaid', 'fk_cpd_pago')
                  ->references('cpa_id')->on('cuota_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('cpd_ccoid', 'fk_cpd_cuota')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['cpd_cpaid', 'cpd_ccoid'], 'uq_cpd_pago_cuota');
            $table->index('cpd_ccoid', 'ix_cpd_cuota');
        });

        DB::statement('ALTER TABLE cuota_pago_detalle ADD CONSTRAINT ck_cpd_monto CHECK (cpd_monto > 0)');

        // Las declaraciones que ya existen pasan a tener una linea.
        DB::statement('
            INSERT INTO cuota_pago_detalle (cpd_cpaid, cpd_ccoid, cpd_monto)
            SELECT cpa_id, cpa_ccoid, cpa_monto FROM cuota_pago WHERE cpa_ccoid IS NOT NULL
        ');

        Schema::table('cuota_pago', function (Blueprint $table) {
            $table->dropForeign('fk_cpa_cco');
            $table->dropColumn('cpa_ccoid');
        });
    }

    public function down(): void
    {
        Schema::table('cuota_pago', function (Blueprint $table) {
            $table->unsignedBigInteger('cpa_ccoid')->nullable();

            $table->foreign('cpa_ccoid', 'fk_cpa_cco')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement('
            UPDATE cuota_pago SET cpa_ccoid = (
                SELECT cpd_ccoid FROM cuota_pago_detalle
                 WHERE cpd_cpaid = cuota_pago.cpa_id LIMIT 1
            )
        ');

        Schema::dropIfExists('cuota_pago_detalle');
    }
};