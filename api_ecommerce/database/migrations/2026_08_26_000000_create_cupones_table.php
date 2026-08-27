<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index();          // codigo que digita el cliente, ej: PROMOCIONES2024
            $table->tinyInteger('type_discount');      // 1 = porcentaje, 2 = monto fijo
            $table->decimal('discount', 10, 2);        // valor del descuento
            $table->tinyInteger('type_count');          // 1 = ilimitado, 2 = limitado
            $table->integer('num_use')->nullable();    // cantidad de usos (solo si type_count = 2)
            $table->tinyInteger('type_cupone');          // 1 = productos, 2 = categorias, 3 = marcas
            $table->tinyInteger('state')->default(1);   // 1 = activo, 2 = inactivo
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
