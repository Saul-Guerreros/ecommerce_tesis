<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->increments('rol_id');
            $table->string('rol_descri', 45);
            $table->tinyInteger('rol_estado')->default(1);
            $table->timestamp('rol_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol');
    }
};
