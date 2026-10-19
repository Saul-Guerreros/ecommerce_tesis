<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Fallas encontradas en el diagnóstico (N por diagnóstico).
 * Unique (dia, falla): la misma falla no se registra dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostico_falla', function (Blueprint $table) {
            $table->id('dfa_id');
            $table->unsignedBigInteger('dfa_diaid');
            $table->unsignedInteger('dfa_fllid');
            $table->string('dfa_obs', 255)->nullable();

            $table->unique(['dfa_diaid', 'dfa_fllid'], 'uq_dfa_dia_fll');

            $table->foreign('dfa_diaid', 'fk_dfa_dia')->references('dia_id')->on('diagnostico')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dfa_fllid', 'fk_dfa_fll')->references('fll_id')->on('falla')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('dfa_fllid', 'ix_dfa_fllid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostico_falla');
    }
};
