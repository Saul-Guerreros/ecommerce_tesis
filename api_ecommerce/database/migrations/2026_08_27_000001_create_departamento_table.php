<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento', function (Blueprint $table) {
            $table->increments('dep_id');
            $table->string('dep_descri', 50);
            $table->tinyInteger('dep_estado')->default(1); // 1 = activo, 0 = inactivo
            $table->timestamp('dep_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento');
    }
};
