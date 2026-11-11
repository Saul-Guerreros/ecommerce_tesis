<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cronograma consolidado de la solicitud.
 *
 * Se persiste y no se calcula al vuelo porque es lo que el cliente
 * ACEPTA en la etapa D: una vez aceptado, el cronograma es un
 * compromiso y no puede cambiar porque alguien toco la configuracion de
 * cuotas del producto. Mismo criterio de snapshot que scd_moncuo.
 *
 * Con planes mixtos el importe baja a medida que las lineas cortas se
 * terminan de pagar: el mes m paga la suma de las lineas cuyo plan
 * llega hasta m o mas. Por eso scc_compos guarda que productos componen
 * esa cuota -- sin ese texto el administrador no puede explicarle al
 * cliente por que la cuota 4 es menor que la 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_credito_cuota', function (Blueprint $table) {
            $table->id('scc_id');
            $table->unsignedBigInteger('scc_scrid');

            $table->unsignedSmallInteger('scc_numero');
            $table->date('scc_fecven');
            $table->decimal('scc_import', 14, 2);
            $table->string('scc_compos', 300)->nullable();

            $table->timestamp('scc_creado')->useCurrent();

            $table->foreign('scc_scrid', 'fk_scc_scr')
                  ->references('scr_id')->on('solicitud_credito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['scc_scrid', 'scc_numero'], 'uq_scc_scr_numero');
            $table->index('scc_scrid', 'ix_scc_scrid');
        });

        DB::statement('ALTER TABLE solicitud_credito_cuota
            ADD CONSTRAINT ck_scc_numero CHECK (scc_numero > 0)');

        DB::statement('ALTER TABLE solicitud_credito_cuota
            ADD CONSTRAINT ck_scc_import CHECK (scc_import > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_credito_cuota');
    }
};