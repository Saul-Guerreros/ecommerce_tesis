<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barrio', function (Blueprint $table) {
            $table->increments('bar_id');
            $table->unsignedInteger('bar_ciuid');
            $table->string('bar_descri', 50);
            $table->smallInteger('bar_estado')->default(1);
            $table->timestamp('bar_creado')->useCurrent();

            $table->foreign('bar_ciuid', 'fk_bar_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            // UNIQUE compuesto: "Centro" o "San Miguel" existen en muchas
            // ciudades; el nombre solo debe ser unico dentro de la ciudad.
            $table->unique(['bar_ciuid', 'bar_descri'], 'uq_bar_ciu_descri');
            $table->index('bar_ciuid', 'ix_bar_ciuid');
        });

        DB::statement("ALTER TABLE barrio
            ADD CONSTRAINT ck_bar_estado CHECK (bar_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('barrio');
    }
};
