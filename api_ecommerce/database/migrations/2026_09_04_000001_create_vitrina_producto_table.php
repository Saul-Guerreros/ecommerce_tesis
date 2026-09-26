<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vitrina_producto: ubicación manual de una publicación_web en las
 * secciones destacadas del home del ecommerce.
 *
 * Reemplaza al intento anterior de un solo campo `pub_destacado_orden`
 * (que nunca llegó a persistirse: no estaba en el $fillable del modelo).
 * Con esta tabla, una misma publicación puede estar en varias secciones
 * a la vez (featured, trending, selling), cada una con su propio orden,
 * y sacarla de una sección es simplemente borrar esa fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vitrina_producto', function (Blueprint $table) {
            $table->increments('vip_id');

            $table->unsignedInteger('vip_pubid');

            // 'featured' | 'trending' | 'selling'
            $table->string('vip_seccion', 20);

            // Posición dentro de la sección (1 = primero).
            $table->unsignedInteger('vip_orden');

            $table->timestamp('vip_creado')->useCurrent();

            // Una publicación no puede estar dos veces en la misma sección.
            $table->unique(['vip_pubid', 'vip_seccion'], 'uq_vitrina_publicacion_seccion');

            $table->foreign('vip_pubid', 'fk_vitrina_publicacion1')
                ->references('pub_id')->on('publicacion_web')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vitrina_producto');
    }
};
