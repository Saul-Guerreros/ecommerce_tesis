<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MÓDULO 6 - ChatBot: mensajes de cada conversación. Además del historial,
 * guarda métricas (modelo, tokens, latencia) para evaluar costo y rendimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_mensaje', function (Blueprint $table) {
            $table->increments('cms_id');
            $table->unsignedInteger('cnv_id');
            $table->string('cms_rol', 12);                  // usuario | asistente | herramienta
            $table->text('cms_contenido')->nullable();
            $table->string('cms_herramienta', 50)->nullable();
            $table->jsonb('cms_datos')->nullable();         // args, resultado, pub_ids mostrados
            $table->string('cms_modelo', 60)->nullable();
            $table->unsignedInteger('cms_tokens_ent')->nullable();
            $table->unsignedInteger('cms_tokens_sal')->nullable();
            $table->unsignedInteger('cms_latencia_ms')->nullable();
            $table->timestamp('cms_creado')->useCurrent();

            $table->foreign('cnv_id')->references('cnv_id')->on('chat_conversacion')->cascadeOnDelete();
            $table->index(['cnv_id', 'cms_id']);            // cargar historial en orden
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensaje');
    }
};