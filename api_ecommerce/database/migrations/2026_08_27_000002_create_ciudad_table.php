<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudad', function (Blueprint $table) {
            $table->increments('ciu_id');
            $table->unsignedInteger('ciu_depid');
            $table->string('ciu_descri', 50);
            $table->tinyInteger('ciu_estado')->default(1);
            $table->timestamp('ciu_creado')->useCurrent();

            $table->foreign('ciu_depid')->references('dep_id')->on('departamento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudad');
    }
};
