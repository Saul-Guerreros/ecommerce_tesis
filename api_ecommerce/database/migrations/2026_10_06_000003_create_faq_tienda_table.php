<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MÓDULO 6 - ChatBot: información de la tienda que el asistente usa como
 * contexto (horarios, envíos, pagos, garantía). Editable desde el admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_tienda', function (Blueprint $table) {
            $table->increments('fqt_id');
            // envios | pagos | garantia | horarios | retiro | general
            $table->string('fqt_tema', 40);
            $table->string('fqt_pregunta', 255);
            $table->text('fqt_respuesta');
            $table->boolean('fqt_activo')->default(true);
            $table->unsignedSmallInteger('fqt_orden')->default(0);
            $table->timestamp('fqt_creado')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_tienda');
    }
};