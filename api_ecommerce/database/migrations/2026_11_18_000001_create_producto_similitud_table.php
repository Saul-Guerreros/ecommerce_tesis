<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo entrenado del recomendador (Filtrado Colaborativo Ítem-Ítem):
 * para cada producto, sus N productos más similares.
 * Lo regenera completo el comando recomendaciones:entrenar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_similitud', function (Blueprint $table) {
            $table->unsignedInteger('psi_prdid');          // producto origen
            $table->unsignedInteger('psi_simid');          // producto similar
            $table->decimal('psi_score', 8, 6);            // similitud coseno 0..1
            $table->unsignedInteger('psi_soporte');        // usuarios en común
            $table->timestamp('psi_calculado')->useCurrent();

            $table->primary(['psi_prdid', 'psi_simid'], 'pk_producto_similitud');

            $table->foreign('psi_prdid', 'fk_psi_producto')
                ->references('prd_id')->on('producto')->cascadeOnDelete();
            $table->foreign('psi_simid', 'fk_psi_similar')
                ->references('prd_id')->on('producto')->cascadeOnDelete();

            $table->index(['psi_prdid', 'psi_score'], 'ix_psi_producto_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_similitud');
    }
};