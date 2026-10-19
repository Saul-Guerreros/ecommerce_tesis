<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Orden de trabajo (OT-2026-00001), 1:1 con la solicitud.
 * Se crea cuando el técnico inicia el diagnóstico (ose_fecini) y se cierra
 * al finalizar la reparación o al declararse no reparable (ose_fecfin).
 *
 * ose_result y ose_proced quedan NULL hasta el cierre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_servicio', function (Blueprint $table) {
            $table->id('ose_id');
            $table->string('ose_numero', 20)->nullable();
            $table->unsignedBigInteger('ose_sseid');
            $table->unsignedInteger('ose_usuid');
            $table->timestamp('ose_fecini')->useCurrent();
            $table->timestamp('ose_fecfin')->nullable();
            $table->text('ose_proced')->nullable();
            $table->string('ose_result', 12)->nullable();
            $table->smallInteger('ose_gardia')->default(0);
            $table->boolean('ose_ajuste')->default(false);
            $table->timestamp('ose_creado')->useCurrent();

            $table->unique('ose_numero', 'uq_ose_numero');
            $table->unique('ose_sseid', 'uq_ose_sseid');

            $table->foreign('ose_sseid', 'fk_ose_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ose_usuid', 'fk_ose_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('ose_usuid', 'ix_ose_usuid');
        });

        DB::statement("ALTER TABLE orden_servicio ADD CONSTRAINT ck_ose_result
            CHECK (ose_result IS NULL OR ose_result IN ('REPARADO', 'PARCIAL', 'NO_REPARABLE'))");
        DB::statement("ALTER TABLE orden_servicio ADD CONSTRAINT ck_ose_gardia CHECK (ose_gardia BETWEEN 0 AND 730)");
        DB::statement("ALTER TABLE orden_servicio ADD CONSTRAINT ck_ose_cierre
            CHECK ((ose_result IS NULL AND ose_fecfin IS NULL) OR (ose_result IS NOT NULL AND ose_fecfin IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio');
    }
};
