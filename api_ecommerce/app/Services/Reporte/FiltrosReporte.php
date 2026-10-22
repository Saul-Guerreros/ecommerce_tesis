<?php

namespace App\Services\Reporte;

use Illuminate\Support\Carbon;

/**
 * Filtros ya validados y normalizados de una consulta de métricas o reportes.
 *
 * Value Object inmutable: el FormRequest lo construye una sola vez y los
 * Services lo reciben tipado, sin leer $request directamente. Así la capa
 * de negocio no depende de HTTP y se puede probar o reutilizar (por ejemplo,
 * desde la exportación PDF/Excel) con los mismos filtros.
 */
final class FiltrosReporte
{
    public const AGRUPACIONES = ['dia', 'mes', 'anio'];

    public function __construct(
        public readonly Carbon $desde,
        public readonly Carbon $hasta,
        public readonly ?int $sucursalId = null,
        public readonly ?int $depositoId = null,
        public readonly string $agrupacion = 'mes',
        public readonly int $limite = 10,
    ) {
    }

    /** Mes en curso hasta hoy: valor por defecto del dashboard. */
    public static function mesActual(?int $sucursalId = null): self
    {
        return new self(
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
            $sucursalId,
        );
    }

    /**
     * Mismo tramo de días del mes anterior (1–20 oct → 1–20 sep).
     * subMonthNoOverflow evita que 31-mar se convierta en 03-mar.
     */
    public function periodoAnterior(): self
    {
        return new self(
            $this->desde->copy()->subMonthNoOverflow()->startOfDay(),
            $this->hasta->copy()->subMonthNoOverflow()->endOfDay(),
            $this->sucursalId,
            $this->depositoId,
            $this->agrupacion,
            $this->limite,
        );
    }

    /** Fechas para columnas DATE (ven_fchaem, com_fchaem). */
    public function rangoFechas(): array
    {
        return [$this->desde->toDateString(), $this->hasta->toDateString()];
    }

    /** Fechas para columnas TIMESTAMP (ped_creado, cnv_creado). */
    public function rangoTimestamps(): array
    {
        return [$this->desde->toDateTimeString(), $this->hasta->toDateTimeString()];
    }

    /** Clave de caché única por combinación de filtros. */
    public function claveCache(string $prefijo): string
    {
        return sprintf(
            'reportes:%s:%s:%s:s%s:d%s:%s:%d',
            $prefijo,
            $this->desde->toDateString(),
            $this->hasta->toDateString(),
            $this->sucursalId ?? 'todas',
            $this->depositoId ?? 'todos',
            $this->agrupacion,
            $this->limite,
        );
    }

    /** Eco de los filtros aplicados, para devolverlos en "meta". */
    public function toArray(): array
    {
        return [
            'desde'       => $this->desde->toDateString(),
            'hasta'       => $this->hasta->toDateString(),
            'sucursal_id' => $this->sucursalId,
            'deposito_id' => $this->depositoId,
            'agrupacion'  => $this->agrupacion,
            'limite'      => $this->limite,
        ];
    }
}