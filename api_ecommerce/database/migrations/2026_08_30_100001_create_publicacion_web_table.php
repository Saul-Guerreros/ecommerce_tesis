<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULO 2 - Catálogo de Productos (según DER).
 *
 * publicacion_web es la "ficha pública" del producto: título y descripción
 * para la web, categoría, estado de publicación (borrador/publicado).
 * Es la separación de "datos de inventario/compra" (tabla producto, ya
 * existente) vs "datos de presentación en la tienda" (esta tabla).
 *
 * NOTA IMPORTANTE: el DER no dibuja una línea directa entre `producto` y
 * `publicacion_web`. Se añade aquí `pub_prdid` como FK única (1:1) porque
 * sin ese campo no habría forma de saber a qué producto pertenece cada
 * publicación. Se definió así tras confirmar contigo la relación 1 a 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicacion_web', function (Blueprint $table) {
            $table->increments('pub_id');

            // Campo agregado respecto al DER: FK 1:1 hacia producto (ver nota arriba).
            $table->unsignedInteger('pub_prdid');

            $table->unsignedInteger('pub_catid');
            $table->string('pub_titulo', 150);
            $table->text('pub_descwb')->nullable();
            // 0 = Borrador, 1 = Publicado (mismo criterio que prd_estado: 0/1)
            $table->smallInteger('pub_estado')->default(0);
            $table->timestamp('pub_creado')->useCurrent();

            // Relación 1:1 con producto: cada producto tiene, a lo sumo, una publicación.
            $table->unique('pub_prdid', 'uq_publicacion_producto');

            $table->foreign('pub_prdid', 'fk_publicacion_producto1')
                ->references('prd_id')->on('producto')
                ->onDelete('cascade');

            $table->foreign('pub_catid', 'fk_publicacion_categoria1')
                ->references('cat_id')->on('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicacion_web');
    }
};
