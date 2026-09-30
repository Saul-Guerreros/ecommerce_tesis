<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla singleton: parámetros de la tienda web que el administrador
     * puede cambiar sin tocar código. Se espera UNA sola fila (mismo
     * criterio que configuracion_precio).
     *
     * Hoy contiene los avisos de stock que se muestran en el Detalle del
     * Producto. Está pensada para sumar más parámetros de la tienda a
     * futuro sin crear una tabla nueva por cada uno.
     *
     * OJO: no es lo mismo que producto.prd_stkmin (stock mínimo). Ese es
     * un umbral INTERNO de reposición que usa el admin ("bajo mínimo");
     * este es lo que se le AVISA al cliente. Son decisiones distintas.
     */
    public function up(): void
    {
        Schema::create('configuracion_tienda', function (Blueprint $table) {
            $table->increments('ctn_id');

            // Aviso "Solo quedan X unidades"
            $table->boolean('ctn_pocstk_activo')->default(true);
            $table->unsignedInteger('ctn_pocstk_umbral')->default(5);

            // Aviso "Máximo disponible: X unidades" al llegar al tope de stock
            $table->boolean('ctn_maxstk_activo')->default(true);

            $table->timestamp('ctn_creado')->useCurrent();
        });

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_pocstk_umbral CHECK (ctn_pocstk_umbral >= 1)");
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_tienda');
    }
};