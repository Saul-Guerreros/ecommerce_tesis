<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupone_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupone_id')->constrained('cupones')->cascadeOnDelete();
            // AJUSTADO a tu esquema real: producto.prd_id es unsignedInteger (increments),
            // no bigIncrements, por eso NO se puede usar foreignId()->constrained() aqui
            // (Postgres exige que los tipos de columna coincidan exactamente en un FK).
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('prd_id')->on('producto')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupone_products');
    }
};
