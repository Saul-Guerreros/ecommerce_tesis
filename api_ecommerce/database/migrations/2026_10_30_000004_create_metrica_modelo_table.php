<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado del backtesting de cada modelo por producto y depósito.
 * Permite justificar qué modelo se eligió y con qué precisión.
 *
 *   mmo_mae     Error absoluto medio (unidades por semana).
 *   mmo_wape    Error porcentual ponderado (0..n). NULL si no hubo demanda
 *               en el periodo de prueba (división por cero).
 *   mmo_elegido true para el modelo usado en prediccion_demanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrica_modelo', function (Blueprint $table) {
            $table->bigIncrements('mmo_id');
            $table->unsignedInteger('mmo_prdid');
            $table->unsignedInteger('mmo_depid');
            $table->string('mmo_modelo', 20);
            $table->decimal('mmo_mae', 14, 4);
            $table->decimal('mmo_wape', 8, 4)->nullable();
            $table->smallInteger('mmo_semprb');           // semanas usadas como prueba
            $table->boolean('mmo_elegido')->default(false);
            $table->timestamp('mmo_creado')->useCurrent();

            $table->foreign('mmo_prdid', 'fk_mmo_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('mmo_depid', 'fk_mmo_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['mmo_prdid', 'mmo_depid', 'mmo_modelo'], 'uq_mmo_prd_dep_mod');
            $table->index(['mmo_modelo', 'mmo_elegido'], 'ix_mmo_modelo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrica_modelo');
    }
};
