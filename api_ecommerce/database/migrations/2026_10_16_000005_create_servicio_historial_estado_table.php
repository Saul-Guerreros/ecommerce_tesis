<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Trazabilidad de los 4 ejes (copia de pedido_historial_estado).
 * she_estant / she_estid apuntan al catálogo que indica she_tipo, por eso
 * no llevan FK (mismo criterio que peh_estid).
 *   SERVICIO -> estado_servicio   INGRESO/EGRESO -> estado_logistica
 *   PAGO     -> estado_pago       PRESUPUESTO    -> estado_presupuesto
 *   NOTA     -> sin estado (ej. reasignación de técnico, cambio de fecha)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_historial_estado', function (Blueprint $table) {
            $table->id('she_id');
            $table->unsignedBigInteger('she_sseid');
            $table->unsignedInteger('she_usuid')->nullable();
            $table->string('she_tipo', 11);
            $table->unsignedSmallInteger('she_estant')->nullable();
            $table->unsignedSmallInteger('she_estid')->nullable();
            $table->string('she_coment', 500)->nullable();
            $table->string('she_ip', 45)->nullable();
            $table->timestamp('she_fecha')->useCurrent();

            $table->foreign('she_sseid', 'fk_she_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('she_usuid', 'fk_she_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index(['she_sseid', 'she_fecha'], 'ix_she_sse_fecha');
        });

        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_tipo
            CHECK (she_tipo IN ('SERVICIO', 'INGRESO', 'EGRESO', 'PAGO', 'PRESUPUESTO', 'NOTA'))");
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_estid
            CHECK (she_tipo = 'NOTA' OR she_estid IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_historial_estado');
    }
};
