<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa `producto` con los campos que ya estaban en el DER pero no se
 * habían implementado: prd_tivid (tipo de IVA), prd_umdid (unidad de
 * medida) y prd_afestk (si el producto afecta stock, ej. un servicio no
 * afecta).
 *
 * NOTA: prd_tipid (tipo de producto) se sacó de esta migración: al
 * revisar el DER, ese campo ya no forma parte de `producto`. La tabla
 * `tipo_producto` que se había creado solo para esa FK también se quitó
 * del proyecto (ver migración *_create_tipo_producto_table.php, eliminada).
 *
 * Como puede haber productos ya cargados, se resuelven en 3 pasos:
 * 1) crear un registro "por defecto" en cada catálogo nuevo,
 * 2) agregar las columnas nullable y rellenar los productos existentes
 *    con esos valores por defecto,
 * 3) volver las columnas NOT NULL + agregar las FKs.
 * Así la migración no rompe si `producto` ya tiene datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoIvaId = DB::table('tipo_iva')->insertGetId([
            'tiv_descri'  => 'IVA 10%',
            'tiv_tasa'    => 10.00,
            'tiv_estado'  => 1,
        ], 'tiv_id');

        $unidadMedidaId = DB::table('unidad_medida')->insertGetId([
            'umd_codigo'  => 'UN',
            'umd_nombre'  => 'Unidad',
            'umd_decimal' => 0,
            'umd_estado'  => 1,
        ], 'umd_id');

        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_tivid')->nullable()->after('prd_marid');
            $table->unsignedInteger('prd_umdid')->nullable()->after('prd_tivid');
            // afecta stock: 1 = descuenta/suma stock al comprar o vender (default),
            // 0 = producto que no maneja stock (ej. un servicio técnico).
            $table->tinyInteger('prd_afestk')->default(1)->after('prd_estado');
        });

        DB::table('producto')->update([
            'prd_tivid' => $tipoIvaId,
            'prd_umdid' => $unidadMedidaId,
        ]);

        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_tivid')->nullable(false)->change();
            $table->unsignedInteger('prd_umdid')->nullable(false)->change();

            $table->foreign('prd_tivid', 'fk_producto_tipo_iva1')
                ->references('tiv_id')->on('tipo_iva');

            $table->foreign('prd_umdid', 'fk_producto_unidad_medida1')
                ->references('umd_id')->on('unidad_medida');
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropForeign('fk_producto_tipo_iva1');
            $table->dropForeign('fk_producto_unidad_medida1');
            $table->dropColumn(['prd_tivid', 'prd_umdid', 'prd_afestk']);
        });
    }
};
