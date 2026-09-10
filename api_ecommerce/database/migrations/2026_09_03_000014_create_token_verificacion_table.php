<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_verificacion', function (Blueprint $table) {
            $table->increments('tkv_id');
            $table->unsignedInteger('tkv_usuid');
            // verificacion_email, recuperacion_password
            $table->string('tkv_tipo', 20);
            $table->string('tkv_tokhas', 64);
            $table->timestamp('tkv_fecexp');
            $table->tinyInteger('tkv_usado')->default(0);
            $table->timestamp('tkv_creado')->useCurrent();

            $table->foreign('tkv_usuid')->references('usu_id')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_verificacion');
    }
};
