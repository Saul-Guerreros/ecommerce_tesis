<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión inteligente de inventario: parámetros globales del cálculo.
 *
 *   ctn_nivserv   Nivel de servicio objetivo (%). Define el factor Z del stock de seguridad.
 *   ctn_horizonte Semanas a pronosticar (y a cubrir con la cantidad sugerida).
 *   ctn_leadtime  Días de entrega por defecto (si el producto no define prd_ltdias).
 *   ctn_cobmax    Semanas de cobertura a partir de las cuales hay sobrestock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->smallInteger('ctn_nivserv')->default(95);
            $table->smallInteger('ctn_horizonte')->default(4);
            $table->smallInteger('ctn_leadtime')->default(7);
            $table->smallInteger('ctn_cobmax')->default(12);
        });

        // Defensa en la base además de la validación del FormRequest.
        DB::statement('ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_nivserv   CHECK (ctn_nivserv BETWEEN 80 AND 99),
            ADD CONSTRAINT ck_ctn_horizonte CHECK (ctn_horizonte BETWEEN 1 AND 12),
            ADD CONSTRAINT ck_ctn_leadtime  CHECK (ctn_leadtime BETWEEN 1 AND 180),
            ADD CONSTRAINT ck_ctn_cobmax    CHECK (ctn_cobmax BETWEEN 2 AND 52)');
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn(['ctn_nivserv', 'ctn_horizonte', 'ctn_leadtime', 'ctn_cobmax']);
        });
    }
};
