<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las variaciones pasaron al "Sistema B" (cada variante es un producto
 * independiente, ver prd_padre_id) y la tabla variacion_producto ya no
 * existe en las migraciones activas. El carrito ya no necesita apuntar
 * a una variación: la variante elegida es simplemente car_idpro.
 *
 * Con el if(hasColumn) esta migración sirve en dos casos:
 *  - BD que ya corrió el create_carrito viejo -> quita FK y columna.
 *  - BD nueva (migrate:fresh) -> la columna ya no existe, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('carrito', 'car_idvarp')) {
            Schema::table('carrito', function (Blueprint $table) {
                $table->dropForeign(['car_idvarp']);
                $table->dropColumn('car_idvarp');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('carrito', 'car_idvarp')) {
            Schema::table('carrito', function (Blueprint $table) {
                $table->unsignedBigInteger('car_idvarp')->nullable()->after('car_idpro');
            });
        }
    }
};
