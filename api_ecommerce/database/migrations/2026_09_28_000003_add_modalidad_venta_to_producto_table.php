<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidades de venta por producto.
 *
 * Hasta ahora el sistema asumía que TODO producto se vende al por menor
 * y al por mayor: PriceService calculaba siempre prd_pvtmin y prd_pvtmay,
 * aunque la tienda nunca vendiera ese producto en una de las dos formas.
 *
 * Con estos dos flags el admin decide, producto por producto, en qué
 * modalidades se vende. El markup NO se habilita/deshabilita: es la
 * modalidad la que se habilita, y el markup es su configuración.
 *
 * No se reutilizan prd_marmin/prd_marmaj para esto porque en esos campos
 * NULL ya significa otra cosa ("usar el margen general de la tienda");
 * serían dos significados distintos en un mismo campo.
 *
 * Default true en ambas: los productos ya cargados conservan exactamente
 * el comportamiento actual hasta que el admin los ajuste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->boolean('prd_habmin')->default(true)->after('prd_pvtmay');
            $table->boolean('prd_habmaj')->default(true)->after('prd_habmin');
        });

        // Primera de las tres capas de validación (PostgreSQL + Laravel +
        // Angular): un producto sin ninguna modalidad no tiene precio de
        // venta, así que la base misma lo impide.
        DB::statement('ALTER TABLE producto
            ADD CONSTRAINT ck_prd_modalidad_venta CHECK (prd_habmin OR prd_habmaj)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE producto DROP CONSTRAINT IF EXISTS ck_prd_modalidad_venta');

        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn(['prd_habmin', 'prd_habmaj']);
        });
    }
};