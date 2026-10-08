<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MÓDULO 6 - ChatBot: una conversación por visitante.
 * El invitado se identifica por cnv_token (UUID guardado en el navegador);
 * cli_id se completa cuando hay sesión (necesario para estado_pedido, fase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversacion', function (Blueprint $table) {
            $table->increments('cnv_id');
            $table->uuid('cnv_token')->unique();
            $table->unsignedInteger('cli_id')->nullable();
            $table->string('cnv_estado', 15)->default('activa');   // activa | derivada | cerrada
            $table->string('cnv_motivo_deriv', 255)->nullable();
            $table->string('cnv_ip', 45)->nullable();
            $table->timestamp('cnv_creado')->useCurrent();
            $table->timestamp('cnv_ultimo_msj')->useCurrent();

            $table->foreign('cli_id')->references('cli_id')->on('cliente')->nullOnDelete();
            $table->index(['cnv_estado', 'cnv_ultimo_msj']);   // bandeja de derivadas en el admin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversacion');
    }
};