<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index();            // se autogenera con uniqid()
            $table->tinyInteger('type_discount');        // 1 = porcentaje, 2 = monto fijo
            $table->decimal('discount', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->tinyInteger('discount_type');        // 1 = productos, 2 = categorias, 3 = marcas
            $table->tinyInteger('type_campaing');         // 1 = normal, 2 = flash, 3 = link
            $table->tinyInteger('state')->default(1);    // 1 = activo, 2 = inactivo
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
