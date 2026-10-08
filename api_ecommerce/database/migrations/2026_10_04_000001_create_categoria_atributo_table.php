<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULO 2 - Catálogo: Plantillas de Ficha Técnica.
 *
 * Define QUÉ atributos técnicos corresponden a cada subcategoría
 * (ej. Notebooks -> Procesador, RAM, Almacenamiento, GPU). No guarda
 * valores: los valores de cada producto siguen viviendo en
 * especificacion_producto. Esta tabla solo arma el formulario dinámico
 * de la ficha técnica en Publicación Web.
 *
 * Regla: las plantillas se definen solo en SUBCATEGORÍAS (cat_idpadr no
 * nulo), porque las 4 categorías raíz agrupan productos muy distintos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_atributo', function (Blueprint $table) {
            $table->increments('cta_id');
            $table->unsignedInteger('cta_catid');
            $table->unsignedInteger('cta_atbid');
            $table->smallInteger('cta_orden')->default(1);
            // 0 = opcional, 1 = obligatorio (mismo criterio 0/1 que los *_estado)
            $table->smallInteger('cta_obliga')->default(0);
            $table->timestamp('cta_creado')->useCurrent();

            // Un atributo aparece una sola vez en la plantilla de cada categoría.
            $table->unique(['cta_catid', 'cta_atbid'], 'uq_categoria_atributo');

            // Si se borra la categoría, su plantilla deja de tener sentido.
            $table->foreign('cta_catid', 'fk_categoria_atributo_categoria1')
                ->references('cat_id')->on('categoria')
                ->onDelete('cascade');

            $table->foreign('cta_atbid', 'fk_categoria_atributo_atributo1')
                ->references('atb_id')->on('atributo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_atributo');
    }
};
