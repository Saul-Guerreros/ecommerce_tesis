<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Análisis de comportamiento: cada apertura del detalle de un producto.
 * vpr_busid indica que el cliente llegó desde una búsqueda
 * (base de "productos más buscados").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vista_producto', function (Blueprint $table) {
            $table->bigIncrements('vpr_id');
            $table->unsignedInteger('vpr_prdid');
            $table->unsignedBigInteger('vpr_busid')->nullable();
            $table->unsignedInteger('vpr_usuid')->nullable();
            $table->uuid('vpr_visita')->nullable();
            $table->timestamp('vpr_creado')->useCurrent();

            $table->foreign('vpr_prdid', 'fk_vista_producto_producto')
                ->references('prd_id')->on('producto')->cascadeOnDelete();
            $table->foreign('vpr_busid', 'fk_vista_producto_busqueda')
                ->references('bus_id')->on('busqueda')->nullOnDelete();
            $table->foreign('vpr_usuid', 'fk_vista_producto_usuario')
                ->references('usu_id')->on('usuario')->nullOnDelete();

            $table->index(['vpr_prdid', 'vpr_creado'], 'ix_vpr_producto');
            $table->index('vpr_busid', 'ix_vpr_busqueda');
            $table->index(['vpr_usuid', 'vpr_creado'], 'ix_vpr_usuario');
            $table->index('vpr_creado', 'ix_vpr_creado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vista_producto');
    }
};