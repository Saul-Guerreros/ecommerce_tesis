<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intento_sesion', function (Blueprint $table) {
            $table->increments('ise_id');
            // nullable: un intento puede fallar antes de identificar un usuario real
            $table->unsignedInteger('ise_usuid')->nullable();
            $table->string('ise_logusa', 150);
            // 1 = exitoso, 0 = fallido
            $table->tinyInteger('ise_result');
            $table->string('ise_obs', 50)->nullable();
            $table->string('ise_iporig', 45)->nullable();
            $table->string('ise_userag', 255)->nullable();
            $table->timestamp('ise_creado')->useCurrent();

            $table->foreign('ise_usuid')->references('usu_id')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intento_sesion');
    }
};
