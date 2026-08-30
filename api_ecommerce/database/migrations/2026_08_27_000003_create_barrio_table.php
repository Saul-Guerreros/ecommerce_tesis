<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barrio', function (Blueprint $table) {
            $table->increments('bar_id');
            $table->unsignedInteger('bar_ciuid');
            $table->string('bar_descri', 50);
            $table->tinyInteger('bar_estado')->default(1);
            $table->timestamp('bar_creado')->useCurrent();

            $table->foreign('bar_ciuid')->references('ciu_id')->on('ciudad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barrio');
    }
};
