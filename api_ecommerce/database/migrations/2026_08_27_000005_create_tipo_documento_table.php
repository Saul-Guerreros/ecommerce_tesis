<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_documento', function (Blueprint $table) {
            $table->increments('tpd_id');
            $table->string('tpd_codigo', 10);
            $table->string('tpd_descri', 50);
            $table->tinyInteger('tpd_estado')->default(1);
            $table->dateTime('tpd_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_documento');
    }
};
