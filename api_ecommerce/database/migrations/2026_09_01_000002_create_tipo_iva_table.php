<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tipo_iva, tal como está en el DER: tiv_id, tiv_descri, tiv_tasa,
 * tiv_estado. Usada por `producto.prd_tivid` y por `compra_detalle.cmd_tivid`
 * (esta última fuera del alcance de este módulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_iva', function (Blueprint $table) {
            $table->increments('tiv_id');
            $table->string('tiv_descri', 45);
            $table->decimal('tiv_tasa', 5, 2);
            $table->tinyInteger('tiv_estado')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_iva');
    }
};
