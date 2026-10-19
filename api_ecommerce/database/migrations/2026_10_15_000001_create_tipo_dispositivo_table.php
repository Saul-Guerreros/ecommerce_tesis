<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Servicio Técnico. Catálogo de tipos de equipo que recibe el taller
 * (Notebook, PC de escritorio, Impresora...). Lo usa solicitud_servicio
 * (sse_tdiid) y filtra las fallas sugeridas (falla.fll_tdiid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_dispositivo', function (Blueprint $table) {
            $table->smallIncrements('tdi_id');
            $table->string('tdi_codigo', 20);
            $table->string('tdi_descri', 50);
            $table->smallInteger('tdi_estado')->default(1);
            $table->timestamp('tdi_creado')->useCurrent();

            $table->unique('tdi_codigo', 'uq_tdi_codigo');
            $table->unique('tdi_descri', 'uq_tdi_descri');
        });

        DB::statement("ALTER TABLE tipo_dispositivo ADD CONSTRAINT ck_tdi_estado CHECK (tdi_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_dispositivo');
    }
};
