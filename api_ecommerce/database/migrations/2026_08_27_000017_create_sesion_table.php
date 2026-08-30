<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesion', function (Blueprint $table) {
            $table->increments('ses_id');
            $table->unsignedInteger('ses_usuid');
            // hash SHA-256 (hex) del JWT emitido, 64 caracteres - no se guarda el token en texto plano
            $table->string('ses_tokhas', 64);
            $table->string('ses_iporig', 45)->nullable();
            $table->string('ses_userag', 255)->nullable();
            $table->timestamp('ses_fecexp');
            $table->tinyInteger('ses_revoca')->default(0);
            $table->timestamp('ses_creado')->useCurrent();

            $table->foreign('ses_usuid')->references('usu_id')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion');
    }
};
