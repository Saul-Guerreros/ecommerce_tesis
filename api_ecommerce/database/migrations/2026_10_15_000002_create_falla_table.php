<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Catálogo de fallas que el técnico marca en el diagnóstico
 * (diagnostico_falla.dfa_fllid).
 *
 * fll_tdiid nullable: NULL = falla genérica que aplica a cualquier equipo
 * (ej. "No enciende"); con valor = falla propia de un tipo (ej. "Atasco de papel").
 * Es maestro (increments) y no catálogo chico: crece con el uso del taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('falla', function (Blueprint $table) {
            $table->increments('fll_id');
            $table->string('fll_codigo', 20);
            $table->string('fll_descri', 100);
            $table->unsignedSmallInteger('fll_tdiid')->nullable();
            $table->smallInteger('fll_estado')->default(1);
            $table->timestamp('fll_creado')->useCurrent();

            $table->unique('fll_codigo', 'uq_fll_codigo');

            $table->foreign('fll_tdiid', 'fk_fll_tdi')
                  ->references('tdi_id')->on('tipo_dispositivo')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('fll_tdiid', 'ix_fll_tdiid');
        });

        DB::statement("ALTER TABLE falla ADD CONSTRAINT ck_fll_estado CHECK (fll_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('falla');
    }
};
