<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor general del plazo de retiro en sucursal.
 *
 * Default true: la funcionalidad ya está activa en producción desde que
 * se implementó, así que apagarla tiene que ser una decisión explícita
 * del admin, no un efecto secundario de esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->boolean('ctn_retactivo')->default(true)->after('ctn_pocstk_umbral');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn('ctn_retactivo');
        });
    }
};