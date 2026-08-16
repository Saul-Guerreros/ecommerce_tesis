<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas originales de tu script BD_ECOMMERCE:
 *   prp_id, prp_atbid, prp_nombre, prp_codigo
 *
 * Columnas agregadas para que el CRUD funcione:
 *   prp_creado    -> fecha de creación
 *   prp_eliminado -> soft delete
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedad', function (Blueprint $table) {
            $table->increments('prp_id');

            $table->unsignedInteger('prp_atbid');
            $table->foreign('prp_atbid')
                ->references('atb_id')
                ->on('atributo');

            $table->string('prp_nombre', 50)->nullable();
            $table->string('prp_codigo', 20);

            $table->timestamp('prp_creado')->nullable();    // agregado
            $table->timestamp('prp_eliminado')->nullable(); // agregado (soft delete)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedad');
    }
};
