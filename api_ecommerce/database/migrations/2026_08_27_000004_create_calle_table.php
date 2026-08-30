<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calle', function (Blueprint $table) {
            $table->increments('cal_id');
            $table->unsignedInteger('cal_ciuid');
            $table->string('cal_descri', 80);
            $table->tinyInteger('cal_estado')->default(1);
            $table->timestamp('cal_creado')->useCurrent();

            $table->foreign('cal_ciuid')->references('ciu_id')->on('ciudad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calle');
    }
};
