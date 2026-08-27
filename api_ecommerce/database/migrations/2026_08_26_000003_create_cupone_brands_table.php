<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupone_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupone_id')->constrained('cupones')->cascadeOnDelete();
            // Confirmado con tu migracion real: marca.mar_id es unsignedInteger.
            $table->unsignedInteger('brand_id');
            $table->foreign('brand_id')->references('mar_id')->on('marca')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupone_brands');
    }
};
