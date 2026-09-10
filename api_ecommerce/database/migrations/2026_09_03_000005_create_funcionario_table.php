<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionario', function (Blueprint $table) {
            $table->increments('fun_id');
            $table->unsignedInteger('fun_perid');
            $table->unsignedInteger('fun_crgid');
            $table->date('fun_fecing');
            $table->date('fun_fecegr')->nullable();
            $table->smallInteger('fun_estado')->default(1);
            $table->timestamp('fun_creado')->useCurrent();

            $table->foreign('fun_perid')->references('per_id')->on('persona');
            $table->foreign('fun_crgid')->references('crg_id')->on('cargo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionario');
    }
};
