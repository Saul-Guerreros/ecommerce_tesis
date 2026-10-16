<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Productos solicitados a credito, congelados al generar la solicitud.
 *
 * Espeja a carrito en su parte de financiacion (car_precio ->
 * scd_preuni, car_subtot -> scd_subtot, car_montocuo -> scd_moncuo),
 * porque el carrito se vacia apenas la solicitud queda generada: si no
 * se copian aca, el administrador no tendria nada que evaluar.
 *
 * scd_descri guarda el titulo del producto al momento de solicitar.
 * El nombre comercial puede cambiar y la solicitud debe seguir
 * mostrando lo que el cliente vio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_credito_detalle', function (Blueprint $table) {
            $table->id('scd_id');
            $table->unsignedBigInteger('scd_scrid');
            $table->unsignedInteger('scd_prdid');

            $table->string('scd_descri', 150);
            $table->decimal('scd_cantid', 14, 2);
            $table->decimal('scd_preuni', 14, 2);          // precio al contado, unitario
            $table->unsignedSmallInteger('scd_numcuo');    // plan elegido para esta linea
            $table->decimal('scd_recpct', 5, 2);           // % de recargo aplicado
            $table->decimal('scd_moncuo', 14, 2);          // cuota POR UNIDAD (= car_montocuo)
            $table->decimal('scd_subtot', 14, 2);          // financiado x cantidad (= car_total)
            $table->decimal('scd_iva', 14, 2)->default(0); // IVA contenido en scd_subtot

            $table->foreign('scd_scrid', 'fk_scd_scr')
                  ->references('scr_id')->on('solicitud_credito')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('scd_prdid', 'fk_scd_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('scd_scrid', 'ix_scd_scrid');
        });

        DB::statement("ALTER TABLE solicitud_credito_detalle ADD CONSTRAINT ck_scd_cantid
            CHECK (scd_cantid > 0)");

        DB::statement("ALTER TABLE solicitud_credito_detalle ADD CONSTRAINT ck_scd_numcuo
            CHECK (scd_numcuo > 0)");

        DB::statement("ALTER TABLE solicitud_credito_detalle ADD CONSTRAINT ck_scd_recpct
            CHECK (scd_recpct >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_credito_detalle');
    }
};