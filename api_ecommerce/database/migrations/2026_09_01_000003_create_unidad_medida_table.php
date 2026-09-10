<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unidad_medida, tal como está en el DER: umd_id, umd_codigo, umd_nombre,
 * umd_decimal (si la unidad admite decimales, ej. Kg vs Unidad), umd_estado.
 * Usada por `producto.prd_umdid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_medida', function (Blueprint $table) {
            $table->increments('umd_id');
            $table->string('umd_codigo', 5);
            $table->string('umd_nombre', 40);
            $table->tinyInteger('umd_decimal')->default(0);
            $table->tinyInteger('umd_estado')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_medida');
    }
};
