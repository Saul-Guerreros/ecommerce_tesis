<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MODULO 2 - Catálogo: ÍNDICE DE BÚSQUEDA de la tienda.
 *
 * Una fila por publicación PUBLICADA, con toda la información que el
 * buscador necesita ya reunida y "aplanada":
 *  - texto (título, marca, subcategoría, ficha, descripción) para BM25
 *    y trigramas;
 *  - la ficha técnica EFECTIVA en jsonb para los filtros del LLM.
 *
 * ¿Por qué una tabla aparte y no consultar las tablas originales?
 *  - Herencia: una variante no tiene filas propias de RAM/GPU (las
 *    hereda del producto base). Un filtro SQL directo sobre
 *    especificacion_producto no la encontraría; acá la ficha ya está
 *    fusionada.
 *  - Rendimiento: un solo SELECT con índices GIN, sin 6 joins por búsqueda.
 *
 * Precio y stock NO se guardan: cambian con cada compra, venta o campaña
 * de descuento y se resuelven en la consulta, para no quedar desactualizados.
 *
 * Extensiones requeridas (PostgreSQL): unaccent y pg_trgm. Si el usuario
 * de la base no tiene permiso para crearlas, debe hacerlo un superusuario:
 *   CREATE EXTENSION unaccent; CREATE EXTENSION pg_trgm;
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Configuración de búsqueda en español que además ignora tildes:
        // "portatil" encuentra "portátil". Se usa en el tsvector y, más
        // adelante, en la consulta (deben usar la misma configuración).
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = 'es_unaccent') THEN
                    CREATE TEXT SEARCH CONFIGURATION public.es_unaccent (COPY = pg_catalog.spanish);
                    ALTER TEXT SEARCH CONFIGURATION public.es_unaccent
                        ALTER MAPPING FOR hword, hword_part, word
                        WITH public.unaccent, pg_catalog.spanish_stem;
                END IF;
            END
            $$;
        SQL);

        // unaccent() no es IMMUTABLE, por eso no puede usarse en una columna
        // generada. Este envoltorio (patrón estándar en PostgreSQL) sí lo es.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.f_unaccent(text)
            RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent', $1) $$;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TABLE indice_busqueda (
                ibu_pubid        integer PRIMARY KEY
                                 REFERENCES publicacion_web (pub_id) ON DELETE CASCADE,
                ibu_prdid        integer NOT NULL,
                ibu_catid        integer NULL,      -- subcategoría
                ibu_catpadr      integer NULL,      -- categoría principal
                ibu_marid        integer NULL,

                -- Campos de texto, separados por importancia (peso en el ranking)
                ibu_titulo       varchar(150) NOT NULL,
                ibu_marca        varchar(100) NULL,
                ibu_categoria    varchar(160) NULL, -- "Notebooks Informática"
                ibu_ficha_texto  text NULL,         -- "Procesador: Intel Core i7 | Memoria RAM: 16 GB | ..."
                ibu_descripcion  text NULL,

                -- Ficha efectiva estructurada para filtros (claves = atb_id):
                -- {"12": {"num": 16}, "15": {"txt": "RTX 4050"}, "18": {"prp": [4, 7]}}
                ibu_ficha        jsonb NOT NULL DEFAULT '{}'::jsonb,

                -- Documento para BM25 / full-text. Pesos: A título, B marca y
                -- subcategoría, C ficha técnica, D descripción.
                ibu_vector       tsvector GENERATED ALWAYS AS (
                      setweight(to_tsvector('public.es_unaccent', coalesce(ibu_titulo, '')), 'A')
                   || setweight(to_tsvector('public.es_unaccent', coalesce(ibu_marca, '') || ' ' || coalesce(ibu_categoria, '')), 'B')
                   || setweight(to_tsvector('public.es_unaccent', coalesce(ibu_ficha_texto, '')), 'C')
                   || setweight(to_tsvector('public.es_unaccent', coalesce(ibu_descripcion, '')), 'D')
                ) STORED,

                -- Texto corto normalizado (minúsculas, sin tildes) para los
                -- trigramas: tolera errores de tipeo ("notbook", "lenobo").
                ibu_texto_corto  text GENERATED ALWAYS AS (
                    lower(public.f_unaccent(
                        coalesce(ibu_titulo, '') || ' ' || coalesce(ibu_marca, '') || ' ' || coalesce(ibu_categoria, '')
                    ))
                ) STORED,

                ibu_actualizado  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        DB::statement('CREATE INDEX idx_indice_busqueda_vector ON indice_busqueda USING GIN (ibu_vector)');
        DB::statement('CREATE INDEX idx_indice_busqueda_trgm ON indice_busqueda USING GIN (ibu_texto_corto gin_trgm_ops)');
        DB::statement('CREATE INDEX idx_indice_busqueda_ficha ON indice_busqueda USING GIN (ibu_ficha jsonb_path_ops)');
        DB::statement('CREATE INDEX idx_indice_busqueda_catid ON indice_busqueda (ibu_catid)');
        DB::statement('CREATE INDEX idx_indice_busqueda_catpadr ON indice_busqueda (ibu_catpadr)');
        DB::statement('CREATE INDEX idx_indice_busqueda_marid ON indice_busqueda (ibu_marid)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS indice_busqueda');
        DB::statement('DROP FUNCTION IF EXISTS public.f_unaccent(text)');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS public.es_unaccent');
        // Las extensiones se dejan: otras partes de la base podrían usarlas.
    }
};
