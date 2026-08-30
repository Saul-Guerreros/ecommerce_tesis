<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_password', function (Blueprint $table) {
            $table->increments('hpa_id');
            $table->string('hpa_pasant', 60);
            $table->string('hpa_iporig', 45)->nullable();
            $table->timestamp('hpa_creado')->useCurrent();
            $table->unsignedInteger('hpa_usuid');

            $table->foreign('hpa_usuid')->references('usu_id')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_password');
    }
};
