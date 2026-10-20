<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El envío (modo/costo/gratis_desde) se muda a su propio módulo:
 * configuracion_envio + tarifa_envio, que agrega el modo "Según sucursal".
 * configuracion_tienda vuelve a ser solo avisos de stock.
 */
return new class extends Migration
{
    public function up(): void
{
    DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_fijo_costo');
    DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_gratis_desde');
    DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_costo');
    DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_modo');

    Schema::table('configuracion_tienda', function (Blueprint $table) {
        foreach (['ctn_envio_modo', 'ctn_envio_costo', 'ctn_envio_gratis_desde'] as $col) {
            if (Schema::hasColumn('configuracion_tienda', $col)) {
                $table->dropColumn($col);
            }
        }
    });
}

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->string('ctn_envio_modo', 10)->default('fijo');
            $table->decimal('ctn_envio_costo', 14, 2)->default(0);
            $table->decimal('ctn_envio_gratis_desde', 14, 2)->default(0);
        });
    }
};