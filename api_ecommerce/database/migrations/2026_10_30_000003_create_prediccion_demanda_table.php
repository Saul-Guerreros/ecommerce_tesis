<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pronóstico semanal de demanda por producto y depósito.
 * Solo se guarda el pronóstico del modelo ELEGIDO (menor error en el
 * backtesting); cada recálculo reemplaza los periodos futuros.
 *
 *   pdd_periodo  Lunes de la semana pronosticada (date_trunc('week')).
 *   pdd_cantid   Unidades previstas para esa semana (unidad de stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediccion_demanda', function (Blueprint $table) {
            $table->bigIncrements('pdd_id');
            $table->unsignedInteger('pdd_prdid');
            $table->unsignedInteger('pdd_depid');
            $table->date('pdd_periodo');
            $table->decimal('pdd_cantid', 14, 4);
            $table->string('pdd_modelo', 20);
            $table->timestamp('pdd_creado')->useCurrent();

            $table->foreign('pdd_prdid', 'fk_pdd_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pdd_depid', 'fk_pdd_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['pdd_prdid', 'pdd_depid', 'pdd_periodo'], 'uq_pdd_prd_dep_per');
            $table->index('pdd_depid', 'ix_pdd_depid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediccion_demanda');
    }
};
