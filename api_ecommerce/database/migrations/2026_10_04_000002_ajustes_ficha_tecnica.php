<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MODULO 2 - Catálogo: ajustes previos a la Ficha Técnica.
 *
 * 1) atributo.atb_unidad: unidad de medida de los atributos numéricos
 *    (GB, pulgadas, GHz, W). Permite guardar "RAM = 16" como número en
 *    esp_valnum y mostrarlo como "16 GB". Al ser número, el buscador/LLM
 *    puede filtrar con comparaciones (ram >= 16), cosa que con texto no.
 *
 * 2) especificacion_producto.esp_prpid pasa a NULLABLE: los atributos de
 *    tipo Texto y Número guardan su valor en esp_valibr / esp_valnum y no
 *    necesitan una propiedad. Solo Seleccionable / Selección múltiple la usan.
 *
 * Se usa ALTER TABLE directo (PostgreSQL) para no depender de
 * doctrine/dbal, que Laravel 10 exige para ->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atributo', function (Blueprint $table) {
            $table->string('atb_unidad', 15)->nullable()->after('atb_tipo');
        });

        DB::statement('ALTER TABLE especificacion_producto ALTER COLUMN esp_prpid DROP NOT NULL');
    }

    public function down(): void
    {
        // Volver a NOT NULL falla si ya existen especificaciones de tipo
        // Texto/Número (sin propiedad). Se eliminan antes de revertir.
        DB::table('especificacion_producto')->whereNull('esp_prpid')->delete();
        DB::statement('ALTER TABLE especificacion_producto ALTER COLUMN esp_prpid SET NOT NULL');

        Schema::table('atributo', function (Blueprint $table) {
            $table->dropColumn('atb_unidad');
        });
    }
};
