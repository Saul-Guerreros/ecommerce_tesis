<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El aviso "Máximo disponible: X unidades" dejó de ser una decisión de
 * negocio configurable: mostraba el stock EXACTO a cualquiera que probara
 * cantidades altas, lo cual filtra información interna de inventario sin
 * ningún beneficio comercial (a diferencia de "poco stock", que sí es una
 * táctica de urgencia de compra intencional).
 *
 * A partir de ahora, cuando la cantidad pedida supera el stock disponible,
 * el frontend siempre corrige la cantidad y muestra un mensaje genérico
 * ("No hay más stock disponible"), sin revelar el número real y sin
 * depender de ninguna configuración del admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn('ctn_maxstk_activo');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->boolean('ctn_maxstk_activo')->default(true);
        });
    }
};