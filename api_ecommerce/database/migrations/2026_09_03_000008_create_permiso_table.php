<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso', function (Blueprint $table) {
            $table->increments('prm_id');
            $table->string('prm_descri', 45);
            $table->tinyInteger('prm_estado')->default(1);
            $table->timestamp('prm_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso');
    }
};
