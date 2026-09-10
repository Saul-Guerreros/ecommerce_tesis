<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo', function (Blueprint $table) {
            $table->increments('crg_id');
            $table->string('crg_descr', 80);
            $table->tinyInteger('crg_estado')->default(1);
            $table->dateTime('crg_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo');
    }
};
