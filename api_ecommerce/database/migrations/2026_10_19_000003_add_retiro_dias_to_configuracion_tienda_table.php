<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días hábiles que el cliente tiene para retirar su pedido en sucursal,
 * contados desde que el pedido pasa a LISTO.
 *
 * Va en configuracion_tienda y no en sucursal porque es una política
 * comercial de la tienda, no una característica de cada local: lo que
 * varía por sucursal son los días laborables (suc_diaslab), que definen
 * CÓMO se cuentan esos días, no CUÁNTOS son.
 *
 * El aviso previo al vencimiento (ctn_retaviso) es opcional y se expresa
 * en días antes del vencimiento. 0 = no avisar, para que la funcionalidad
 * pueda quedar apagada mientras las notificaciones no estén listas sin
 * que haya que tocar código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->smallInteger('ctn_retdias')->default(5)->after('ctn_pocstk_umbral');
            $table->smallInteger('ctn_retaviso')->default(1)->after('ctn_retdias');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn(['ctn_retdias', 'ctn_retaviso']);
        });
    }
};