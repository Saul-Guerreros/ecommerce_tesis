<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Refuerza publicacion_web con lo que le faltaba para un módulo de
 * catálogo web "profesional" (ver análisis previo):
 *
 * - pub_prevta: precio de venta propio de la ficha web. Es intencional
 *   que quede separado de producto.prd_prevta (precio de lista/costo
 *   interno): permite tener un precio publicado distinto (ofertas,
 *   redondeo comercial) sin tocar el precio de inventario/compra.
 * - pub_slug: URL amigable para la ficha (/producto/mi-producto-slug),
 *   única, autogenerada desde pub_titulo si no se manda una.
 * - pub_meta_titulo / pub_meta_descripcion: SEO básico (title tag / meta
 *   description) que toda plataforma de e-commerce seria maneja aparte
 *   del título visible.
 * - pub_actualizado: timestamp de última edición de la ficha (antes solo
 *   existía pub_creado, sin forma de auditar cambios de contenido).
 *
 * pub_estado no cambia de tipo: pasa de manejar 2 valores (0 borrador,
 * 1 publicado) a 3 (agregando 2 = archivado), ya era smallint así que
 * no hace falta migración de esquema para eso, solo de validación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->decimal('pub_prevta', 14, 2)->nullable()->after('pub_titulo');
            $table->string('pub_slug', 180)->nullable()->after('pub_prevta');
            $table->string('pub_meta_titulo', 160)->nullable()->after('pub_descwb');
            $table->string('pub_meta_descripcion', 320)->nullable()->after('pub_meta_titulo');
            $table->timestamp('pub_actualizado')->nullable()->after('pub_creado');
        });

        // Backfill de slugs para publicaciones ya existentes, garantizando unicidad.
        $usados = [];
        foreach (DB::table('publicacion_web')->orderBy('pub_id')->get() as $publicacion) {
            $base = Str::slug($publicacion->pub_titulo) ?: 'producto';
            $slug = $base;
            $sufijo = 1;

            while (in_array($slug, $usados, true)) {
                $slug = "{$base}-{$sufijo}";
                $sufijo++;
            }

            $usados[] = $slug;

            DB::table('publicacion_web')
                ->where('pub_id', $publicacion->pub_id)
                ->update(['pub_slug' => $slug]);
        }

        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->string('pub_slug', 180)->nullable(false)->change();
            $table->unique('pub_slug', 'uq_publicacion_slug');
        });
    }

    public function down(): void
    {
        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->dropUnique('uq_publicacion_slug');
            $table->dropColumn([
                'pub_prevta',
                'pub_slug',
                'pub_meta_titulo',
                'pub_meta_descripcion',
                'pub_actualizado',
            ]);
        });
    }
};
