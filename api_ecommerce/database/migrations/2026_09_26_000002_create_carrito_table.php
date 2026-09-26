<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrito', function (Blueprint $table) {
            $table->id('car_id');

            $table->unsignedBigInteger('car_idusu'); // id_usuario
            $table->unsignedBigInteger('car_idpro'); // id_producto

            $table->unsignedTinyInteger('car_tipdes')->nullable(); // tipo_descuento
            $table->double('car_descue')->default(0);              // descuento

            $table->unsignedTinyInteger('car_tipcam')->nullable(); // tipo_campania
            $table->string('car_codcup', 250)->nullable();         // codigo_cupon
            $table->string('car_coddes', 50)->nullable();          // codigo_descuento

            $table->unsignedBigInteger('car_idvarp')->nullable();  // id_variacion_producto

            $table->double('car_cantid')->default(1); // cantidad
            $table->double('car_precio');              // precio_unitario
            $table->double('car_subtot');               // subtotal
            $table->double('car_total');                 // total

            $table->timestamp('car_creado')->nullable(); // creado_en
            $table->timestamp('car_actual')->nullable(); // actualizado_en
            $table->timestamp('car_elimin')->nullable(); // eliminado_en (soft delete manual)

            // Relaciones (FK)
            $table->foreign('car_idusu')->references('usu_id')->on('usuario');
            $table->foreign('car_idpro')->references('prd_id')->on('producto');
            $table->foreign('car_idvarp')->references('var_proid')->on('variacion_producto');        
        
        });
    }

    public function down(): void
    {
        Schema::table('carrito', function (Blueprint $table) {
            $table->dropForeign(['car_idusu']);
            $table->dropForeign(['car_idpro']);
            $table->dropForeign(['car_idvarp']);
        });

        Schema::dropIfExists('carrito');
    }
};