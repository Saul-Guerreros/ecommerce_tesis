<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo dependiente de tipo_profesion. El frontend filtra las
 * opciones del segundo desplegable con pro_tprid, sin volver a pedir
 * nada al backend: el endpoint de configuracion devuelve las dos
 * listas completas de una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesion', function (Blueprint $table) {
            $table->increments('pro_id');
            $table->unsignedSmallInteger('pro_tprid');
            $table->string('pro_descri', 80);
            $table->smallInteger('pro_estado')->default(1);
            $table->timestamp('pro_creado')->useCurrent();

            $table->foreign('pro_tprid', 'fk_pro_tpr')
                  ->references('tpr_id')->on('tipo_profesion')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['pro_tprid', 'pro_descri'], 'uq_pro_tpr_descri');
           $table->index('pro_tprid', 'ix_profesion_pro_tprid');
        });

        DB::statement("ALTER TABLE profesion
            ADD CONSTRAINT ck_pro_estado CHECK (pro_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('profesion');
    }
};