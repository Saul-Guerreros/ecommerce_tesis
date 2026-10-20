<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de la factura: entidad debil de venta_cab, snapshot del articulo.
 * Se copian codigo, descripcion y precio; no se leen del producto al
 * imprimir, porque el producto puede haber cambiado de nombre o de precio.
 *
 * vnd_prdid es NULLABLE a proposito. La unica linea sin producto es el
 * costo de envio: ped_cosenv forma parte de ped_total y, si no se
 * facturara, el total de la factura no coincidiria con lo que pago el
 * cliente. La alternativa (crear un producto ficticio "Servicio de envio")
 * ensuciaria el inventario con un articulo que nunca entra ni sale del
 * deposito.
 *
 * No se incluye vnd_varid: variacion_producto no forma parte del alcance
 * actual del checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_detalle', function (Blueprint $table) {
            $table->increments('vnd_id');
            $table->unsignedInteger('vnd_venid');
            $table->unsignedInteger('vnd_prdid')->nullable();
            $table->smallInteger('vnd_nroitem');

            $table->string('vnd_codigo', 45);
            $table->string('vnd_descripcion', 150);
            $table->string('vnd_umedida', 15)->default('UN');
            $table->string('vnd_nroserie', 60)->nullable();

            $table->decimal('vnd_cantidad', 14, 4);
            $table->decimal('vnd_precio', 14, 4);        // unitario CON IVA incluido
            $table->decimal('vnd_descuento', 14, 2)->default(0);

            $table->decimal('vnd_tasa', 5, 2);           // 0 / 5 / 10
            $table->decimal('vnd_valorventa', 14, 2);    // (precio * cantidad) - descuento
            $table->decimal('vnd_iva', 14, 2)->default(0);

            $table->foreign('vnd_venid', 'fk_vnd_ven')->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('vnd_prdid', 'fk_vnd_prd')->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['vnd_venid', 'vnd_nroitem'], 'uq_vnd_item');
            $table->index('vnd_prdid', 'ix_vnd_producto');
        });

        DB::statement("ALTER TABLE venta_detalle ADD CONSTRAINT ck_vnd_cant CHECK (vnd_cantidad > 0)");
        DB::statement("ALTER TABLE venta_detalle ADD CONSTRAINT ck_vnd_desc CHECK (vnd_descuento >= 0)");
        DB::statement("ALTER TABLE venta_detalle ADD CONSTRAINT ck_vnd_tasa CHECK (vnd_tasa IN (0, 5, 10))");
        DB::statement("ALTER TABLE venta_detalle ADD CONSTRAINT ck_vnd_iva  CHECK (vnd_tasa > 0 OR vnd_iva = 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalle');
    }
};