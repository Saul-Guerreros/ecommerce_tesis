<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('usu_id');
            $table->string('usu_login', 30)->unique();
            $table->string('usu_passwd', 60);
            // ACTIVO, INACTIVO (pendiente de verificar email), BLOQUEADO
            $table->string('usu_estado', 15)->default('INACTIVO');
            $table->timestamp('usu_creado')->useCurrent();
            $table->unsignedInteger('usu_perid');
            $table->unsignedInteger('usu_rolid');

            $table->foreign('usu_perid')->references('per_id')->on('persona');
            $table->foreign('usu_rolid')->references('rol_id')->on('rol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
