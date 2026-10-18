<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilidad de la sucursal en la tienda web.
 *
 *  - suc_webstk   : aparece (o no) en el modal "Ver Stock en Tiendas" del
 *                   detalle de producto. Independiente de suc_estado: una
 *                   sucursal operativa puede no querer exponer su stock.
 *  - suc_delivery : la sucursal despacha envíos a domicilio. Hoy es
 *                   informativo (badge en el modal); queda disponible para
 *                   el módulo de envíos.
 *
 * Ambos nacen en true: el comportamiento por defecto es "la sucursal se
 * muestra y ofrece los dos servicios", y el administrador lo restringe
 * cuando hace falta.
 *
 * suc_retiro ya existía con default false. Se cambia el default a true
 * para las sucursales NUEVAS y se hace backfill SOLO donde hay dirección
 * cargada: SucursalRequest exige dirección cuando el retiro está activo,
 * y además esa dirección es lo que el cliente ve en el Checkout - Paso 1.
 * Activarlo a ciegas dejaría sucursales sin dirección en ese listado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->boolean('suc_webstk')->default(true)->after('suc_retiro');
            $table->boolean('suc_delivery')->default(true)->after('suc_webstk');
        });

        // Las filas existentes quedaron en true por el default de la columna.
        // Solo falta normalizar suc_retiro, que venía en false.
        DB::table('sucursal')
            ->whereNotNull('suc_direccion')
            ->where('suc_direccion', '!=', '')
            ->update(['suc_retiro' => true]);

        Schema::table('sucursal', function (Blueprint $table) {
            $table->boolean('suc_retiro')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->dropColumn(['suc_webstk', 'suc_delivery']);
            $table->boolean('suc_retiro')->default(false)->change();
        });
    }
};