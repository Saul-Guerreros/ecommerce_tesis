<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            // 45 se quedaba corto: "Ajuste de costo por Nota de Crédito " (37)
            // + numero de documento (hasta 15) supera el límite. Se alinea
            // con el resto de las columnas de observación del sistema (100).
            $table->string('mst_obs', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->string('mst_obs', 45)->nullable()->change();
        });
    }
};