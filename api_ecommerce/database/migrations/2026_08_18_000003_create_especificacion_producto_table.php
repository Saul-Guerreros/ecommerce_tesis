<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTA: en tu DDL original, esp_id no tenía AUTO_INCREMENT. Aquí se corrige
 * porque sin eso los inserts fallarían al no poder generar el ID automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especificacion_producto', function (Blueprint $table) {
            $table->increments('esp_id');
            $table->unsignedInteger('esp_atbid');
            $table->unsignedInteger('esp_proid');
            $table->unsignedInteger('esp_prpid');
            $table->string('esp_valibr', 150)->nullable();
            $table->decimal('esp_valnum', 12, 2)->nullable();
            $table->timestamp('esp_creado')->useCurrent();

            $table->foreign('esp_atbid')->references('atb_id')->on('atributo');
            $table->foreign('esp_proid')->references('prd_id')->on('producto');
            $table->foreign('esp_prpid')->references('prp_id')->on('propiedad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especificacion_producto');
    }
};
