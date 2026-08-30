<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direccion', function (Blueprint $table) {
            $table->increments('dir_id');
            $table->unsignedInteger('dir_perid');
            $table->unsignedInteger('dir_barid');
            $table->unsignedInteger('dir_calid');
            $table->string('dir_nrocasa', 45)->nullable();
            $table->string('dir_referencia', 100)->nullable();
            $table->string('dir_celular', 20)->nullable();
            $table->string('dir_telef', 20)->nullable();
            // Una persona puede tener varias direcciones; esta marca cual es la default
            $table->tinyInteger('dir_predet')->default(0);
            $table->tinyInteger('dir_estado')->default(1);
            $table->timestamp('dir_creado')->useCurrent();
            $table->timestamp('dir_modific')->nullable();

            $table->foreign('dir_perid')->references('per_id')->on('persona');
            $table->foreign('dir_barid')->references('bar_id')->on('barrio');
            $table->foreign('dir_calid')->references('cal_id')->on('calle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direccion');
    }
};
