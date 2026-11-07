<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de contacto que muestra la tienda (página Contacto y footer).
 * Singleton: una sola fila. El mapa se guarda como coordenadas (no como
 * <iframe> pegado), así la tienda arma la URL y nunca inserta HTML ajeno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datos_contacto', function (Blueprint $table) {
            $table->increments('dco_id');
            $table->string('dco_titulo', 120);
            $table->string('dco_intro', 500)->nullable();
            $table->string('dco_direccion', 255)->nullable();
            $table->string('dco_referencia', 255)->nullable();
            $table->string('dco_telefono', 30)->nullable();
            $table->string('dco_email', 120)->nullable();
            $table->string('dco_whatsapp', 15)->nullable();          // solo dígitos, formato internacional
            $table->string('dco_whatsapp_mensaje', 200)->nullable(); // texto precargado del chat
            $table->string('dco_horario', 500)->nullable();          // multilínea
            $table->decimal('dco_mapa_lat', 10, 7)->nullable();
            $table->decimal('dco_mapa_lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('dco_mapa_zoom')->default(16);
            $table->string('dco_facebook', 255)->nullable();
            $table->string('dco_instagram', 255)->nullable();
            $table->string('dco_youtube', 255)->nullable();
            $table->string('dco_tiktok', 255)->nullable();
            $table->boolean('dco_publicado')->default(true);
            $table->unsignedInteger('dco_usuid')->nullable();
            $table->timestamp('dco_actualizado')->useCurrent();

            $table->foreign('dco_usuid')->references('usu_id')->on('usuario')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_contacto');
    }
};