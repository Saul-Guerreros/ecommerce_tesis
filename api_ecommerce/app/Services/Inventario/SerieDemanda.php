<?php
// app/Services/Inventario/SerieDemanda.php
namespace App\Services\Inventario;

/**
 * Serie semanal de demanda de un producto en un depósito.
 * Objeto inmutable: lo produce SerieDemandaService y lo consumen
 * los modelos de pronóstico (Fase 3).
 *
 * $valores: ['2026-01-05' => 3.0, '2026-01-12' => 0.0, ...]
 * (clave = lunes de la semana, valor = unidades vendidas).
 */
final class SerieDemanda
{
    /** Umbral de Syntetos & Boylan (2005) para demanda intermitente. */
    public const ADI_INTERMITENTE = 1.32;

    /** Mínimos para poder clasificar y pronosticar con un modelo. */
    public const MIN_SEMANAS = 8;
    public const MIN_VENTAS  = 2;

    public const CLASE_INSUFICIENTE = 'insuficiente';
    public const CLASE_INTERMITENTE = 'intermitente';
    public const CLASE_REGULAR      = 'regular';

    /** @param array<string, float> $valores */
    public function __construct(
        public readonly int $productoId,
        public readonly int $depositoId,
        public readonly array $valores,
    ) {
    }

    /** @return float[] Unidades en orden cronológico. */
    public function datos(): array
    {
        return array_values($this->valores);
    }

    /** @return string[] Lunes de cada semana (Y-m-d). */
    public function semanas(): array
    {
        return array_keys($this->valores);
    }

    public function longitud(): int
    {
        return count($this->valores);
    }

    public function total(): float
    {
        return (float) array_sum($this->valores);
    }

    public function semanasConVenta(): int
    {
        return count(array_filter($this->valores, fn (float $v) => $v > 0));
    }

    public function porcentajeCeros(): float
    {
        $n = $this->longitud();

        return $n > 0 ? round(100 * ($n - $this->semanasConVenta()) / $n, 2) : 0.0;
    }

    /**
     * ADI (Average Demand Interval): cada cuántas semanas, en promedio, hay venta.
     * 1.0 = vende todas las semanas; 4.0 = vende una de cada cuatro.
     */
    public function adi(): ?float
    {
        $conVenta = $this->semanasConVenta();

        return $conVenta > 0 ? round($this->longitud() / $conVenta, 4) : null;
    }

    public function esIntermitente(): bool
    {
        $adi = $this->adi();

        return $adi !== null && $adi > self::ADI_INTERMITENTE;
    }

    public function tieneHistorialSuficiente(): bool
    {
        return $this->longitud() >= self::MIN_SEMANAS
            && $this->semanasConVenta() >= self::MIN_VENTAS;
    }

    public function clasificacion(): string
    {
        if (! $this->tieneHistorialSuficiente()) {
            return self::CLASE_INSUFICIENTE;
        }

        return $this->esIntermitente() ? self::CLASE_INTERMITENTE : self::CLASE_REGULAR;
    }

    public function estaVacia(): bool
    {
        return $this->longitud() === 0;
    }
}