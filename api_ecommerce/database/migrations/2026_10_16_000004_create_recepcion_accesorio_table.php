<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Checklist de accesorios recibidos con el equipo. rac_devuel se marca
 * en la entrega (A5): lo que entró tiene que salir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_accesorio', function (Blueprint $table) {
            $table->id('rac_id');
            $table->unsignedBigInteger('rac_rseid');
            $table->string('rac_descri', 100);
            $table->boolean('rac_devuel')->default(false);

            $table->foreign('rac_rseid', 'fk_rac_rse')->references('rse_id')->on('recepcion_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->index('rac_rseid', 'ix_rac_rseid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_accesorio');
    }
};
