<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `sld_posicion` a la tabla `slider`.
 *
 * Solo aplica a banners secundarios (sld_tipo = 2): indica en qué
 * carrusel del home se debe mostrar.
 *   1 = Izquierda
 *   2 = Derecha
 *
 * Para banners principales (sld_tipo = 1) esta columna queda en null,
 * no se usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slider', function (Blueprint $table) {
            $table->smallInteger('sld_posicion')->nullable()->after('sld_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('slider', function (Blueprint $table) {
            $table->dropColumn('sld_posicion');
        });
    }
};
