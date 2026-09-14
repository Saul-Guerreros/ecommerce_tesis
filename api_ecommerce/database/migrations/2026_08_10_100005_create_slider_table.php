<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración completa de la tabla `slider`.
 *
 * Incluye:
 *  - Las columnas originales de tu script BD_ECOMMERCE (sld_id, sld_prdid,
 *    sld_titulo, sld_imagen, sld_enlace, sld_orden, sld_tipo, sld_estado, sld_creado)
 *  - Las columnas nuevas agregadas para el CRUD (sld_subtitulo, sld_label,
 *    sld_color, sld_eliminado)
 *
 * IMPORTANTE:
 *  - Usa esta migración si vas a crear la tabla `slider` desde cero en tu
 *    nueva base PostgreSQL. Requiere que la tabla `producto` ya exista
 *    (por la foreign key sld_prdid -> producto.prd_id), así que debe
 *    ejecutarse DESPUÉS de la migración de `producto`.
 *  - Si tu tabla `slider` ya existe (no es tu caso al migrar a Postgres desde
 *    cero, pero sí en tu MySQL actual), usa en su lugar la migración
 *    "add_extra_fields_to_slider_table" (Schema::table, no Schema::create)
 *    que agrega solo las columnas nuevas. No uses ambas migraciones sobre
 *    la misma base de datos.
 */



return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slider', function (Blueprint $table) {
            $table->id('sld_id');

            $table->unsignedInteger('sld_prdid');
            $table->foreign('sld_prdid')
                ->references('prd_id')
                ->on('producto');

            $table->string('sld_titulo', 150)->nullable();
            $table->string('sld_subtitulo', 150)->nullable(); 
            $table->string('sld_label', 100)->nullable();     
            $table->string('sld_imagen', 255)->nullable();
            $table->string('sld_enlace', 255)->nullable();
            $table->smallInteger('sld_orden')->nullable();
            $table->smallInteger('sld_tipo')->nullable();
            $table->tinyInteger('sld_estado')->nullable();
            $table->string('sld_color', 20)->nullable();      

            $table->timestamp('sld_creado')->nullable();
            $table->timestamp('sld_eliminado')->nullable();  
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slider');
    }
};
