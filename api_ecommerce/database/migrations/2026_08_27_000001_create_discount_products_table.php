<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            // unsignedInteger (no foreignId) porque producto.prd_id es
            // "increments" y en Postgres el tipo debe coincidir exacto.
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('prd_id')->on('producto')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_products');
    }
};
