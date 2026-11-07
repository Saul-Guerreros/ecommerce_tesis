<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promedio y cantidad de reseñas VISIBLES, desnormalizados en la ficha web.
 *
 * Se recalculan en ResenaService::recalcular() cada vez que una reseña se
 * crea, edita, oculta, muestra o elimina. Así el Home, la búsqueda y el
 * detalle muestran el rating sin consultar la tabla resena (sin N+1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->decimal('pub_rating', 2, 1)->nullable();
            $table->unsignedInteger('pub_nresen')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->dropColumn(['pub_rating', 'pub_nresen']);
        });
    }
};