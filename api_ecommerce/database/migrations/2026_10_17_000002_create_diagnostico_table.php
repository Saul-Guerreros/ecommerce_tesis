<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Diagnóstico técnico (1:1 con la OT).
 * dia_tiempo = días estimados de reparación (NULL si no es reparable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostico', function (Blueprint $table) {
            $table->id('dia_id');
            $table->unsignedBigInteger('dia_oseid');
            $table->unsignedInteger('dia_usuid');
            $table->string('dia_causa', 500);
            $table->boolean('dia_repara');
            $table->boolean('dia_reqrep')->default(false);
            $table->smallInteger('dia_tiempo')->nullable();
            $table->timestamp('dia_fecha')->useCurrent();

            $table->unique('dia_oseid', 'uq_dia_oseid');

            $table->foreign('dia_oseid', 'fk_dia_ose')->references('ose_id')->on('orden_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dia_usuid', 'fk_dia_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        // Coherencia: un equipo no reparable no lleva repuestos ni tiempo estimado.
        DB::statement("ALTER TABLE diagnostico ADD CONSTRAINT ck_dia_coherencia
            CHECK (dia_repara = true OR (dia_reqrep = false AND dia_tiempo IS NULL))");
        DB::statement("ALTER TABLE diagnostico ADD CONSTRAINT ck_dia_tiempo
            CHECK (dia_tiempo IS NULL OR dia_tiempo BETWEEN 1 AND 365)");
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostico');
    }
};
