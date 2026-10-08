<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vocabulario de la tienda: todas las palabras que aparecen en el índice
 * de búsqueda (título, marca, categoría, ficha y descripción), en
 * minúsculas y sin tildes.
 *
 * Lo usa la CORRECCIÓN ORTOGRÁFICA del buscador: si el cliente escribe
 * una palabra que no existe en la tienda ("notbook"), se busca la palabra
 * real más parecida por trigramas ("notebook") y se le muestra
 * "Mostrando resultados para notebook". Corregir contra el vocabulario
 * propio (y no contra un diccionario general) garantiza que la
 * corrección siempre lleve a productos que existen.
 *
 * Es una vista materializada: se recalcula al reindexar
 * (IndiceBusquedaService::refrescarVocabulario).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE MATERIALIZED VIEW busqueda_vocabulario AS
            SELECT word AS bvo_palabra,
                   ndoc AS bvo_docs        -- en cuántos productos aparece
            FROM ts_stat($q$
                SELECT to_tsvector('simple',
                    ibu_texto_corto || ' ' ||
                    lower(public.f_unaccent(coalesce(ibu_ficha_texto, '') || ' ' || coalesce(ibu_descripcion, '')))
                )
              FROM public.indice_busqueda
            $q$)
            WHERE length(word) >= 3
        SQL);

        // El índice único permite REFRESH ... CONCURRENTLY (no bloquea lecturas).
        DB::statement('CREATE UNIQUE INDEX uq_busqueda_vocabulario ON busqueda_vocabulario (bvo_palabra)');
        DB::statement('CREATE INDEX idx_busqueda_vocabulario_trgm ON busqueda_vocabulario USING GIN (bvo_palabra gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS busqueda_vocabulario');
    }
};
