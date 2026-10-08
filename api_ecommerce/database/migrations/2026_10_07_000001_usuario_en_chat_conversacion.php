<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MÓDULO 6 - ChatBot: la conversación se vincula al USUARIO autenticado
 * (como carrito.car_idusu), no a cliente, que no se obtiene desde el token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversacion', function (Blueprint $table) {
            $table->dropForeign(['cli_id']);
            $table->dropColumn('cli_id');
        });

        Schema::table('chat_conversacion', function (Blueprint $table) {
            $table->unsignedBigInteger('usu_id')->nullable();
            $table->foreign('usu_id')->references('usu_id')->on('usuario')->nullOnDelete();
            $table->index('usu_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversacion', function (Blueprint $table) {
            $table->dropForeign(['usu_id']);
            $table->dropColumn('usu_id');
        });

        Schema::table('chat_conversacion', function (Blueprint $table) {
            $table->unsignedInteger('cli_id')->nullable();
            $table->foreign('cli_id')->references('cli_id')->on('cliente')->nullOnDelete();
        });
    }
};