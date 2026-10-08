<?php

namespace App\Services\Catalogo;

/**
 * Lo que el LLM entendió de la consulta del cliente, ya VALIDADO contra el
 * catálogo real (InterpreteConsultaService::sanear): todos los ids existen.
 *
 * Ejemplo — "notebook para arquitectos de menos de 5 millones":
 *   consultaReescrita = "notebook ryzen core i7 rtx"
 *   categorias        = [7]                        (Notebooks)
 *   precioMax         = 5000000
 *   especificaciones  = [['atb_id' => 12, 'minimo' => 16, 'maximo' => null, 'opciones' => []]]
 *   explicacion       = "Notebooks con 16 GB de RAM o más, hasta Gs. 5.000.000"
 */
final class InterpretacionConsulta
{
    /**
     * @param int[] $categorias subcategorías (cat_id)
     * @param int[] $marcas     marcas pedidas (mar_id)
     * @param int[] $marcasExcluidas marcas que el cliente NO quiere ("que no sea Lenovo")
     * @param array<int, array{atb_id:int, minimo:?float, maximo:?float, opciones:int[]}> $especificaciones
     */
    public function __construct(
        public readonly string $consultaReescrita,
        public readonly array $categorias,
        public readonly array $marcas,
        public readonly ?float $precioMin,
        public readonly ?float $precioMax,
        public readonly array $especificaciones,
        public readonly string $explicacion,
        public readonly array $marcasExcluidas = [],
    ) {
    }

    public function tieneFiltrosDeIndice(): bool
    {
        return ! empty($this->categorias) || ! empty($this->marcas)
            || ! empty($this->marcasExcluidas) || ! empty($this->especificaciones);
    }

    /** Copia sin filtros técnicos (para relajar la búsqueda si no hubo resultados). */
    public function sinEspecificaciones(): self
    {
        return new self(
            $this->consultaReescrita, $this->categorias, $this->marcas,
            $this->precioMin, $this->precioMax, [], $this->explicacion, $this->marcasExcluidas
        );
    }

    public function toArray(): array
    {
        return [
            'consulta_reescrita' => $this->consultaReescrita,
            'categorias'         => $this->categorias,
            'marcas'             => $this->marcas,
            'marcas_excluidas'   => $this->marcasExcluidas,
            'precio_min'         => $this->precioMin,
            'precio_max'         => $this->precioMax,
            'especificaciones'   => $this->especificaciones,
            'explicacion'        => $this->explicacion,
        ];
    }

    public static function fromArray(array $d): self
    {
        return new self(
            $d['consulta_reescrita'] ?? '',
            $d['categorias'] ?? [],
            $d['marcas'] ?? [],
            $d['precio_min'] ?? null,
            $d['precio_max'] ?? null,
            $d['especificaciones'] ?? [],
            $d['explicacion'] ?? '',
            $d['marcas_excluidas'] ?? [],
        );
    }
}
