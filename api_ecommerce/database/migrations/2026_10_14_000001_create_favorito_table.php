<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * favorito: lista de deseos del cliente (un producto por usuario, una sola vez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorito', function (Blueprint $table) {
            $table->increments('fav_id');

            // Mismos tipos que usuario.usu_id y producto.prd_id (increments).
            $table->unsignedInteger('fav_idusu');
            $table->unsignedInteger('fav_idpro');

            $table->timestamp('fav_creado')->useCurrent();

            // Un producto no puede estar dos veces en la lista del mismo usuario.
            $table->unique(['fav_idusu', 'fav_idpro'], 'uq_favorito_usuario_producto');

            $table->foreign('fav_idusu', 'fk_favorito_usuario1')
                ->references('usu_id')->on('usuario')
                ->onDelete('cascade');

            $table->foreign('fav_idpro', 'fk_favorito_producto1')
                ->references('prd_id')->on('producto')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorito');
    }
};