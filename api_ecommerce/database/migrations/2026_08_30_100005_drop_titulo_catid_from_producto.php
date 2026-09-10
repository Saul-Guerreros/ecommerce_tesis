<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * producto deja de tener título y categoría propios: a partir de ahora
 * viven en publicacion_web (pub_titulo, pub_catid). producto queda solo
 * con datos de inventario/compra (código, descripción interna, precio,
 * stock, marca, estado).
 *
 * Corre DESPUÉS del backfill (2026_08_30_100002), así que los datos ya
 * están copiados a publicacion_web antes de perderse aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropForeign('fk_producto_categoria1');
            $table->dropColumn(['prd_catid', 'prd_titulo']);
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_catid')->nullable()->after('prd_marid');
            $table->string('prd_titulo', 45)->nullable()->after('prd_catid');

            $table->foreign('prd_catid', 'fk_producto_categoria1')
                ->references('cat_id')->on('categoria');
        });
    }
};
