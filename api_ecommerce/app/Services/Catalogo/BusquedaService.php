<?php

namespace App\Services\Catalogo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Motor de búsqueda léxico de la tienda: BM25 + trigramas sobre la tabla
 * indice_busqueda.
 *
 * Flujo de una consulta:
 *   1. Normalizar  : minúsculas, sin tildes, separar en términos.
 *   2. Corregir    : cada término que no existe en el vocabulario de la
 *                    tienda se reemplaza por la palabra real más parecida
 *                    (trigramas): "notbook" -> "notebook".
 *   3. BM25        : se puntúan los productos que contienen algún término
 *                    (por prefijo, para que funcione mientras se escribe:
 *                    "noteb" ya encuentra "notebook"). Se ordena primero
 *                    por CUÁNTOS términos contiene y luego por BM25.
 *   4. Respaldo    : si no hay coincidencias de palabras, se busca por
 *                    parecido del texto completo (trigramas).
 *
 * BM25 (Robertson y Zaragoza, 2009), implementado en SQL:
 *   score(D,Q) = Σ IDF(t) · tf·(k1+1) / (tf + k1·(1 − b + b·|D|/avgdl))
 *   IDF(t)     = ln(1 + (N − df + 0.5) / (df + 0.5))
 * con tres adaptaciones para fichas de producto:
 *   - tf ponderado por campo (variante tipo BM25F): una aparición en el
 *     título o la subcategoría vale más que en la descripción.
 *   - |D| = palabras de título + marca + subcategoría. Así una ficha
 *     técnica completa no penaliza al producto por "documento largo".
 *   - Campo "tipo de producto" = PRIMERA palabra del título, con peso
 *     propio. Los títulos siguen la convención "Tipo + Marca + Modelo"
 *     (ver guía de carga): en "Notebook Lenovo IdeaPad" el producto ES una
 *     notebook; en "Base refrigerante para notebook" solo la MENCIONA.
 *     Sin este campo, BM25 premiaba a los accesorios por tener títulos
 *     más cortos y aparecían antes que las propias notebooks.
 */
class BusquedaService
{
    /** Saturación de tf. Más alto = repetir un término pesa más (títulos cortos). */
    private const K1 = 2.0;
    /** Normalización por longitud. Bajo: los títulos tienen largos parecidos. */
    private const B = 0.4;
    /** Peso de cada campo (letras de setweight en indice_busqueda.ibu_vector). */
    private const PESO_TITULO = 3.0;        // A
    private const PESO_MARCA_CATEGORIA = 3.0; // B
    private const PESO_FICHA = 1.5;         // C
    private const PESO_DESCRIPCION = 1.0;   // D
    /** Peso extra si el término es la primera palabra del título (tipo de producto). */
    private const PESO_TIPO_PRODUCTO = 6.0;

    /** Similitud mínima para corregir una palabra ("lenobo" -> "lenovo" = 0.40). */
    private const UMBRAL_CORRECCION = 0.35;
    /** Similitud mínima del respaldo por trigramas sobre el texto completo. */
    private const UMBRAL_TRIGRAMA = 0.4;
    /** Términos máximos por consulta (protege contra consultas abusivas). */
    private const MAX_TERMINOS = 8;
    /** Resultados máximos que devuelve el motor (luego se filtra y pagina). */
    public const LIMITE_DEFECTO = 500;

    public function buscar(string $consulta, int $limite = self::LIMITE_DEFECTO): ResultadoBusqueda
    {
        $terminos = $this->normalizar($consulta);
        if (empty($terminos)) {
            return ResultadoBusqueda::vacio();
        }

        [$corregidos, $huboCorreccion] = $this->corregir($terminos);
        $texto = implode(' ', $corregidos);
        $corregida = $huboCorreccion ? $texto : null;

        $filas = $this->bm25($texto, $limite);

        if (! empty($filas)) {
            $modo = $filas[0]->terminos === $filas[0]->terminos_consulta
                ? ResultadoBusqueda::TODOS
                : ResultadoBusqueda::PARCIAL;

            return $this->armarResultado($filas, 'puntaje', $modo, $corregida);
        }

        $filas = $this->trigramas($texto, $limite);

        return empty($filas)
            ? new ResultadoBusqueda([], [], ResultadoBusqueda::SIN_RESULTADOS, $corregida)
            : $this->armarResultado($filas, 'similitud', ResultadoBusqueda::APROXIMADO, $corregida);
    }

    // =====================================================================
    // Filtros estructurados (los produce la capa LLM)
    // =====================================================================

    /**
     * pub_id que cumplen los filtros de una interpretación, o null si la
     * interpretación no trae filtros de índice. Trabaja sobre la ficha
     * EFECTIVA del índice (incluye lo heredado por las variantes).
     * Un producto sin el dato en su ficha NO cumple un filtro técnico.
     */
    public function filtrarIndice(InterpretacionConsulta $interpretacion): ?array
    {
        if (! $interpretacion->tieneFiltrosDeIndice()) {
            return null;
        }

        $consulta = DB::table('indice_busqueda')->select('ibu_pubid');

        if ($interpretacion->categorias) {
            $consulta->whereIn('ibu_catid', $interpretacion->categorias);
        }

        if ($interpretacion->marcas) {
            $consulta->whereIn('ibu_marid', $interpretacion->marcas);
        }

        if ($interpretacion->marcasExcluidas) {
            // Los productos sin marca cargada no se excluyen.
            $consulta->where(fn ($q) => $q->whereNull('ibu_marid')
                ->orWhereNotIn('ibu_marid', $interpretacion->marcasExcluidas));
        }

        foreach ($interpretacion->especificaciones as $filtro) {
            // Clave del atributo en el jsonb: {"12": {"num": 16}, "18": {"prp": [4, 7]}}
            $clave = (string) (int) $filtro['atb_id'];

            if ($filtro['minimo'] !== null) {
                $consulta->whereRaw("CAST(ibu_ficha -> CAST(? AS text) ->> 'num' AS numeric) >= ?", [$clave, $filtro['minimo']]);
            }

            if ($filtro['maximo'] !== null) {
                $consulta->whereRaw("CAST(ibu_ficha -> CAST(? AS text) ->> 'num' AS numeric) <= ?", [$clave, $filtro['maximo']]);
            }

            if (! empty($filtro['opciones'])) {
                // Cumple si tiene AL MENOS una de las opciones pedidas.
                $consulta->whereRaw(
                    "EXISTS (SELECT 1 FROM jsonb_array_elements_text(ibu_ficha -> CAST(? AS text) -> 'prp') AS op(id)
                             WHERE CAST(op.id AS integer) = ANY(CAST(? AS integer[])))",
                    [$clave, '{' . implode(',', array_map('intval', $filtro['opciones'])) . '}']
                );
            }
        }

        return $consulta->orderBy('ibu_pubid')->pluck('ibu_pubid')->map(fn ($id) => (int) $id)->all();
    }

    // =====================================================================
    // 1. Normalización
    // =====================================================================

    /** "Notebook  LENOVO, portátil!" -> ['notebook', 'lenovo', 'portatil'] */
    private function normalizar(string $consulta): array
    {
        $texto = Str::of($consulta)->ascii()->lower()->value();
        $terminos = preg_split('/[^a-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY);

        return array_slice(array_values(array_unique($terminos)), 0, self::MAX_TERMINOS);
    }

    // =====================================================================
    // 2. Corrección ortográfica contra el vocabulario de la tienda
    // =====================================================================

    /** @return array{0: string[], 1: bool} términos corregidos y si hubo cambios */
    private function corregir(array $terminos): array
    {
        // Una sola consulta: qué términos son palabras vacías ("para", "con")
        // y cuáles ya existen en la tienda (también como prefijo: "noteb").
        // Los términos son solo [a-z0-9], por eso es seguro armar el arreglo.
        $estado = collect(DB::select(<<<'SQL'
            SELECT t AS termino,
                   to_tsvector('public.es_unaccent', t) = ''::tsvector AS es_vacia,
                   EXISTS (SELECT 1 FROM busqueda_vocabulario v WHERE v.bvo_palabra LIKE t || '%') AS conocido
            FROM unnest(CAST(? AS text[])) AS t
        SQL, ['{' . implode(',', $terminos) . '}']))->keyBy('termino');

        $huboCorreccion = false;

        $corregidos = array_map(function (string $termino) use ($estado, &$huboCorreccion) {
            $info = $estado->get($termino);

            $noSeCorrige = strlen($termino) < 3             // "hp", "i7"
                || preg_match('/\d/', $termino)             // modelos y números: "2775", "16gb"
                || ($info && ($info->es_vacia || $info->conocido));

            if ($noSeCorrige) {
                return $termino;
            }

            $sugerida = DB::selectOne(<<<'SQL'
                SELECT bvo_palabra, similarity(bvo_palabra, ?) AS similitud
                FROM busqueda_vocabulario
                WHERE bvo_palabra % ?
                ORDER BY similitud DESC, bvo_docs DESC
                LIMIT 1
            SQL, [$termino, $termino]);

            if ($sugerida && $sugerida->similitud >= self::UMBRAL_CORRECCION) {
                $huboCorreccion = true;
                return $sugerida->bvo_palabra;
            }

            return $termino;
        }, $terminos);

        return [$corregidos, $huboCorreccion];
    }

    // =====================================================================
    // 3. BM25
    // =====================================================================

    private function bm25(string $texto, int $limite): array
    {
        $sql = <<<'SQL'
            WITH params AS (
                -- Parámetros del modelo (llegan desde las constantes de la clase).
                SELECT CAST(:k1 AS float) AS k1, CAST(:b AS float) AS b,
                       CAST(:peso_a AS float) AS peso_a, CAST(:peso_b AS float) AS peso_b,
                       CAST(:peso_c AS float) AS peso_c, CAST(:peso_d AS float) AS peso_d,
                       CAST(:peso_tipo AS float) AS peso_tipo
            ),
            qt AS (
                -- Términos de la consulta ya procesados como en el índice
                -- (español, sin tildes, raíz): "notebooks" -> "notebook".
                SELECT DISTINCT lexeme AS qlex
                FROM unnest(to_tsvector('public.es_unaccent', :texto))
            ),
            consulta AS (
                -- Prefijo + OR: usa el índice GIN para traer candidatos.
                SELECT to_tsquery('simple', string_agg(quote_literal(qlex) || ':*', ' | ')) AS q
                FROM qt
            ),
            largos AS (
                -- |D|: palabras de título + marca + subcategoría (pesos A y B).
                SELECT i.ibu_pubid,
                       greatest((SELECT count(*) FROM unnest(i.ibu_vector) u, unnest(u.weights) w
                                 WHERE w IN ('A', 'B')), 1)::float AS dl
                FROM indice_busqueda i
            ),
            stats AS (
                SELECT count(*)::float AS n, greatest(avg(dl), 1)::float AS avgdl FROM largos
            ),
            df AS (
                -- En cuántos productos aparece cada término (para el IDF).
                SELECT qt.qlex, count(DISTINCT i.ibu_pubid)::float AS df
                FROM indice_busqueda i
                CROSS JOIN LATERAL unnest(i.ibu_vector) u
                JOIN qt ON u.lexeme LIKE qt.qlex || '%'
                GROUP BY qt.qlex
            ),
            tf AS (
                -- Frecuencia del término en cada producto, ponderada por campo.
                -- Posición 1 = primera palabra del título = tipo de producto.
                SELECT i.ibu_pubid, qt.qlex,
                       sum((SELECT sum(CASE o.w WHEN 'A' THEN p.peso_a WHEN 'B' THEN p.peso_b
                                                WHEN 'C' THEN p.peso_c ELSE p.peso_d END
                                       + CASE WHEN o.pos = 1 THEN p.peso_tipo ELSE 0 END)
                            FROM unnest(u.positions, u.weights) AS o(pos, w))) AS tf
                FROM indice_busqueda i
                CROSS JOIN consulta c
                CROSS JOIN params p
                CROSS JOIN LATERAL unnest(i.ibu_vector) u
                JOIN qt ON u.lexeme LIKE qt.qlex || '%'
                WHERE i.ibu_vector @@ c.q
                GROUP BY i.ibu_pubid, qt.qlex
            )
            SELECT tf.ibu_pubid,
                   count(*)::int AS terminos,
                   (SELECT count(*) FROM qt)::int AS terminos_consulta,
                   sum(
                       ln(1 + (s.n - df.df + 0.5) / (df.df + 0.5))
                       * (tf.tf * (p.k1 + 1))
                       / (tf.tf + p.k1 * (1 - p.b + p.b * l.dl / s.avgdl))
                   ) AS puntaje
            FROM tf
            JOIN df USING (qlex)
            JOIN largos l USING (ibu_pubid)
            CROSS JOIN stats s
            CROSS JOIN params p
            GROUP BY tf.ibu_pubid
            ORDER BY terminos DESC, puntaje DESC
            LIMIT CAST(:limite AS integer)
        SQL;

        return DB::select($sql, [
            'texto'  => $texto,
            'peso_a' => self::PESO_TITULO,
            'peso_b' => self::PESO_MARCA_CATEGORIA,
            'peso_c' => self::PESO_FICHA,
            'peso_d' => self::PESO_DESCRIPCION,
            'peso_tipo' => self::PESO_TIPO_PRODUCTO,
            'k1'     => self::K1,
            'b'      => self::B,
            'limite' => $limite,
        ]);
    }

    // =====================================================================
    // 4. Respaldo por trigramas
    // =====================================================================

    private function trigramas(string $texto, int $limite): array
    {
        // El operador <% usa el índice GIN de trigramas con este umbral.
        DB::statement("SELECT set_config('pg_trgm.word_similarity_threshold', ?, false)", [(string) self::UMBRAL_TRIGRAMA]);

        return DB::select(<<<'SQL'
            SELECT ibu_pubid, word_similarity(?, ibu_texto_corto) AS similitud
            FROM indice_busqueda
            WHERE ? <% ibu_texto_corto
            ORDER BY similitud DESC
            LIMIT CAST(? AS integer)
        SQL, [$texto, $texto, $limite]);
    }

    private function armarResultado(array $filas, string $campoPuntaje, string $modo, ?string $corregida): ResultadoBusqueda
    {
        $ids = array_map(fn ($f) => (int) $f->ibu_pubid, $filas);
        $puntajes = array_combine($ids, array_map(fn ($f) => round((float) $f->{$campoPuntaje}, 4), $filas));

        return new ResultadoBusqueda($ids, $puntajes, $modo, $corregida);
    }
}
