<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La validacion del comprobante pasa a vivir en el propio comprobante.
 *
 * Hasta ahora "verificar" un pago y "cobrarlo" era una sola accion y sus
 * datos se guardaban en pedido_pago (ppa_usuver, ppa_fechver, ppa_observ).
 * Con el ciclo factura -> cobro son dos hechos distintos y con momentos
 * distintos: el admin primero VALIDA el comprobante (esto) y mas tarde, ya
 * con factura, registra el COBRO (cobro_venta).
 *
 * pco_verifi sigue siendo la marca de "validado". Las columnas ppa_* de
 * verificacion dejan de escribirse pero NO se borran: los pedidos viejos
 * conservan su historia ahi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_comprobante', function (Blueprint $table) {
            $table->string('pco_observ', 255)->nullable()->after('pco_verifi');
            $table->unsignedInteger('pco_usuver')->nullable()->after('pco_observ');
            $table->timestamp('pco_fechver')->nullable()->after('pco_usuver');

            $table->foreign('pco_usuver', 'fk_pco_usuver')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        // Los comprobantes ya validados con el flujo viejo conservan quien y
        // cuando: se copian desde su pedido_pago.
        DB::statement('
            UPDATE pago_comprobante pc
               SET pco_observ  = pp.ppa_observ,
                   pco_usuver  = pp.ppa_usuver,
                   pco_fechver = pp.ppa_fechver
              FROM pedido_pago pp
             WHERE pp.ppa_id = pc.pco_ppaid
               AND pc.pco_verifi = true
        ');

        // Coherencia: un comprobante validado debe saber cuando se valido.
        // Se rellena con la fecha de subida para los casos viejos sin dato.
        DB::statement('
            UPDATE pago_comprobante
               SET pco_fechver = pco_subido
             WHERE pco_verifi = true AND pco_fechver IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('pago_comprobante', function (Blueprint $table) {
            $table->dropForeign('fk_pco_usuver');
            $table->dropColumn(['pco_observ', 'pco_usuver', 'pco_fechver']);
        });
    }
};