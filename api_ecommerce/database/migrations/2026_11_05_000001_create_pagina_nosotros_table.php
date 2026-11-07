<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Página "Sobre nosotros" de la tienda (singleton: una sola fila).
 * Todo el contenido es TEXTO PLANO (sin HTML): la tienda lo muestra
 * con interpolación de Angular, así no existe superficie de XSS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagina_nosotros', function (Blueprint $table) {
            $table->increments('pno_id');
            $table->string('pno_titulo', 120);
            $table->string('pno_subtitulo', 255)->nullable();
            $table->text('pno_historia')->nullable();
            $table->string('pno_mision', 600)->nullable();
            $table->string('pno_vision', 600)->nullable();
            $table->json('pno_valores')->nullable();   // [{icono, titulo, descripcion}] máx. 6
            $table->json('pno_cifras')->nullable();    // [{valor, etiqueta}] máx. 4
            $table->string('pno_imagen', 255)->nullable(); // ruta relativa en disk 'public'
            $table->boolean('pno_publicado')->default(true);
            $table->unsignedInteger('pno_usuid')->nullable(); // último usuario que editó
            $table->timestamp('pno_actualizado')->useCurrent();

            $table->foreign('pno_usuid')->references('usu_id')->on('usuario')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagina_nosotros');
    }
};