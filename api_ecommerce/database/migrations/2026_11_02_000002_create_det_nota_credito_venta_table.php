<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de la NC de venta: entidad débil de nota_credito_venta y
 * snapshot del artículo, con la misma forma que venta_detalle.
 *
 * dnv_vndid apunta a la LÍNEA de la factura que se corrige. Es la clave
 * del cálculo de "saldo acreditable": lo acreditado de una línea es la
 * suma de dnv_cantidad de las NC VIGENTES que la referencian.
 *
 * dnv_prdid es nullable por la misma razón que vnd_prdid: la línea del
 * costo de envío no tiene producto y también puede acreditarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('det_nota_credito_venta', function (Blueprint $table) {
            $table->increments('dnv_id');
            $table->unsignedInteger('dnv_ncvid');
            $table->unsignedInteger('dnv_vndid');              // línea original de la factura
            $table->unsignedInteger('dnv_prdid')->nullable();
            $table->smallInteger('dnv_nroitem');

            $table->string('dnv_codigo', 45);
            $table->string('dnv_descripcion', 150);
            $table->string('dnv_umedida', 15)->default('UN');

            $table->decimal('dnv_cantidad', 14, 4);
            $table->decimal('dnv_precio', 14, 4);              // unitario CON IVA incluido
            $table->decimal('dnv_tasa', 5, 2);                 // 0 / 5 / 10
            $table->decimal('dnv_valorventa', 14, 2);          // precio * cantidad
            $table->decimal('dnv_iva', 14, 2)->default(0);     // IVA contenido

            // Cuánto de dnv_cantidad llegó realmente a reingresar al depósito.
            // Puede ser 0: servicios, línea de envío o motivos que no mueven stock.
            $table->decimal('dnv_canrei', 14, 4)->default(0);

            $table->foreign('dnv_ncvid', 'fk_dnv_ncv')->references('ncv_id')->on('nota_credito_venta')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dnv_vndid', 'fk_dnv_vnd')->references('vnd_id')->on('venta_detalle')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dnv_prdid', 'fk_dnv_prd')->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['dnv_ncvid', 'dnv_nroitem'], 'uq_dnv_item');
            $table->index('dnv_vndid', 'ix_dnv_linea');
            $table->index('dnv_prdid', 'ix_dnv_producto');
        });

        DB::statement('ALTER TABLE det_nota_credito_venta ADD CONSTRAINT ck_dnv_cantidad CHECK (dnv_cantidad > 0)');
        DB::statement('ALTER TABLE det_nota_credito_venta ADD CONSTRAINT ck_dnv_precio CHECK (dnv_precio > 0)');
        DB::statement('ALTER TABLE det_nota_credito_venta ADD CONSTRAINT ck_dnv_tasa CHECK (dnv_tasa IN (0, 5, 10))');
        DB::statement('ALTER TABLE det_nota_credito_venta ADD CONSTRAINT ck_dnv_iva CHECK (dnv_tasa > 0 OR dnv_iva = 0)');
        DB::statement('ALTER TABLE det_nota_credito_venta ADD CONSTRAINT ck_dnv_canrei CHECK (dnv_canrei >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('det_nota_credito_venta');
    }
};