<?php

namespace App\Services\Catalogo;

use Illuminate\Support\Str;

/**
 * Orquestador del buscador de la tienda: BM25 + trigramas y, solo cuando
 * hace falta, la capa LLM de rescate.
 *
 *   consulta ──► BM25 + trigramas ──► ¿alcanza? ── sí ──► resultados
 *                                        │
 *                                        no (sin resultados, coincidencia
 *                                        parcial o pide precio/comparación)
 *                                        ▼
 *                       LLM: consulta -> filtros JSON (validados)
 *                                        ▼
 *            BM25 sobre la consulta reescrita ∩ filtros del índice
 *                                        ▼
 *        ¿hay resultados? ── no ──► sin filtros técnicos ── no ──► BM25 original
 *
 * Resultado: las búsquedas simples son instantáneas y gratuitas; el LLM
 * solo interviene en sinónimos, intenciones y filtros en lenguaje natural.
 */
class BuscadorTiendaService
{
    /**
     * Palabras que indican algo que BM25 no puede evaluar: precios y
     * comparaciones ("menos de 4 millones", "más de 24 pulgadas") y
     * exclusiones ("que no sea Lenovo", "excepto HP"). Sin tildes.
     */
    private const PATRON_FILTROS = '/\b(menos|mas|hasta|entre|mayor|menor|minimo|maximo|desde|barat\w*|econom\w*|millon\w*|mil|gs|guaranies|no|excepto|salvo|ni)\b/';

    public function __construct(
        private BusquedaService $motor,
        private InterpreteConsultaService $interprete,
    ) {
    }

    public function buscar(string $consulta, ?string $ip = null): ResultadoBusqueda
    {
        $lexico = $this->motor->buscar($consulta);

        if (! $this->necesitaIA($consulta, $lexico)) {
            return $lexico;
        }

        $interpretacion = $this->interprete->interpretar($consulta, $ip);

        if (! $interpretacion) {
            return $lexico; // IA no disponible: queda BM25
        }

        $resultado = $this->aplicar($interpretacion);

        // Si con los filtros técnicos no hay nada, se prueba sin ellos.
        if ($resultado->total() === 0 && $interpretacion->especificaciones) {
            $resultado = $this->aplicar($interpretacion->sinEspecificaciones(), relajado: true);
        }

        return $resultado->total() > 0 ? $resultado : $lexico;
    }

    /**
     * Anticipa si buscar($consulta) va a esperar al LLM, SIN llamarlo.
     * Lo usa la tienda para mostrar el aviso "Procesando búsqueda con IA"
     * mientras corre la búsqueda real. Devuelve false si no hay LLM
     * configurado o si la interpretación ya está en caché (será instantánea).
     */
    public function usaraIA(string $consulta): bool
    {
        if (! $this->interprete->disponible() || $this->interprete->estaEnCache($consulta)) {
            return false;
        }

        return $this->necesitaIA($consulta, $this->motor->buscar($consulta));
    }

    /** Regla de rescate: ¿la consulta necesita interpretación del LLM? */
    public function necesitaIA(string $consulta, ResultadoBusqueda $lexico): bool
    {
        if ($lexico->modo !== ResultadoBusqueda::TODOS) {
            return true; // sin resultados, coincidencia parcial o aproximada
        }

        $normal = Str::of($consulta)->ascii()->lower()->value();

        return (bool) preg_match(self::PATRON_FILTROS, $normal);
    }

    /**
     * Combina texto y filtros:
     *  - orden: primero los que cumplen los filtros Y coinciden por texto
     *    (en el orden de BM25), después los que solo cumplen los filtros;
     *  - sin filtros de índice: solo el texto reescrito.
     */
    private function aplicar(InterpretacionConsulta $interpretacion, bool $relajado = false): ResultadoBusqueda
    {
        $porTexto = $interpretacion->consultaReescrita !== ''
            ? $this->motor->buscar($interpretacion->consultaReescrita)
            : ResultadoBusqueda::vacio();

        $permitidos = $this->motor->filtrarIndice($interpretacion);

        if ($permitidos === null) {
            $ids = $porTexto->ids;
        } else {
            $cumplen = array_flip($permitidos);
            $coinciden = array_values(array_filter($porTexto->ids, fn ($id) => isset($cumplen[$id])));
            $ids = array_values(array_merge($coinciden, array_diff($permitidos, $coinciden)));
        }

        $puntajes = array_intersect_key($porTexto->puntajes, array_flip($ids));

        return new ResultadoBusqueda(
            ids: $ids,
            puntajes: $puntajes,
            modo: ResultadoBusqueda::IA,
            consultaCorregida: null,
            interpretacion: $interpretacion,
            filtrosRelajados: $relajado,
        );
    }
}
