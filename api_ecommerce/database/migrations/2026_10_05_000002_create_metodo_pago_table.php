<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago', function (Blueprint $table) {
            $table->smallIncrements('mpa_id');
            $table->string('mpa_codigo', 20);
            $table->string('mpa_descri', 50);
            $table->boolean('mpa_requcom')->default(false);
            $table->boolean('mpa_estado')->default(true);
            $table->timestamp('mpa_creado')->useCurrent();

            $table->unique('mpa_codigo', 'uq_mpa_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago');
    }
};