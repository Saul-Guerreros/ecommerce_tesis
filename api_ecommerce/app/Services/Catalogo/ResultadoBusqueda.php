<?php

namespace App\Services\Catalogo;

/**
 * Resultado de BusquedaService::buscar().
 *
 * modo:
 *  - 'todos'         : hay productos que contienen TODOS los términos.
 *  - 'parcial'       : ningún producto los contiene todos; se muestran los
 *                      que contienen alguno (ordenados por cuántos y BM25).
 *  - 'aproximado'    : sin coincidencias de palabras; resultados por
 *                      parecido de texto (trigramas).
 *  - 'sin_resultados': nada.
 *  - 'ia'            : resultados obtenidos con la interpretación del LLM
 *                      (ver BuscadorTiendaService).
 * 'parcial', 'aproximado' y 'sin_resultados' son las señales que activan
 * la capa LLM de rescate.
 */
final class ResultadoBusqueda
{
    public const TODOS = 'todos';
    public const PARCIAL = 'parcial';
    public const APROXIMADO = 'aproximado';
    public const SIN_RESULTADOS = 'sin_resultados';
    public const IA = 'ia';

    /**
     * @param int[]              $ids     pub_id ordenados por relevancia
     * @param array<int, float>  $puntajes pub_id => puntaje
     */
    public function __construct(
        public readonly array $ids,
        public readonly array $puntajes,
        public readonly string $modo,
        public readonly ?string $consultaCorregida = null,
        /** Interpretación del LLM (solo en modo 'ia'). */
        public readonly ?InterpretacionConsulta $interpretacion = null,
        /** true si hubo que quitar los filtros técnicos para obtener resultados. */
        public readonly bool $filtrosRelajados = false,
    ) {
    }

    /** Precio mínimo/máximo que pidió el cliente en lenguaje natural (si la IA lo detectó). */
    public function precioMin(): ?float
    {
        return $this->interpretacion?->precioMin;
    }

    public function precioMax(): ?float
    {
        return $this->interpretacion?->precioMax;
    }

    public static function vacio(): self
    {
        return new self([], [], self::SIN_RESULTADOS);
    }

    public function total(): int
    {
        return count($this->ids);
    }

    /** Posición de cada pub_id (0 = más relevante), para ordenar colecciones. */
    public function posiciones(): array
    {
        return array_flip($this->ids);
    }
}
