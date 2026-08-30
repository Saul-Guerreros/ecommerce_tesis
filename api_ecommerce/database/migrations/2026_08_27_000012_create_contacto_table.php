<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto', function (Blueprint $table) {
            $table->increments('con_id');
            $table->unsignedInteger('con_perid');
            // email, telefono, celular, etc.
            $table->string('con_tipo', 20);
            $table->string('con_valor', 100);
            $table->smallInteger('con_estado')->default(1);
            $table->timestamp('con_creado')->useCurrent();
            $table->timestamp('con_modific')->nullable();

            $table->foreign('con_perid')->references('per_id')->on('persona');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto');
    }
};
