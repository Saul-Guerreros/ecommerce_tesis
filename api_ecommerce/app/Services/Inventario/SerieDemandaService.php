<?php
// app/Services/Inventario/SerieDemandaService.php
namespace App\Services\Inventario;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Construye las series semanales de demanda por producto y depósito
 * a partir de las ventas facturadas.
 *
 * Una sola consulta agrupada para todo el catálogo (evita N+1);
 * el relleno de semanas sin venta se hace en PHP.
 */
class SerieDemandaService
{
    public const SEMANAS_HISTORIAL = 52;
    private const VENTA_ANULADA = 9;

    /**
     * @return array<string, SerieDemanda> Clave "productoId-depositoId".
     *         Solo incluye combinaciones con al menos una venta en la ventana.
     */
    public function obtener(
        int $semanas = self::SEMANAS_HISTORIAL,
        ?int $productoId = null,
        ?int $depositoId = null,
        ?CarbonImmutable $referencia = null,
    ): array {
        [$desde, $hasta] = $this->ventana($semanas, $referencia);
        $calendario      = $this->calendario($desde, $hasta);

        $agrupado = [];
        foreach ($this->consultar($desde, $hasta, $productoId, $depositoId) as $fila) {
            $agrupado["{$fila->prd_id}-{$fila->dep_id}"][$fila->semana] = (float) $fila->unidades;
        }

        $series = [];
        foreach ($agrupado as $clave => $ventas) {
            [$prd, $dep] = array_map('intval', explode('-', $clave));
            $series[$clave] = new SerieDemanda($prd, $dep, $this->completar($calendario, $ventas));
        }

        return $series;
    }

    /** Serie de un único producto/depósito (vacía si no tuvo ventas). */
    public function deProducto(
        int $productoId,
        int $depositoId,
        int $semanas = self::SEMANAS_HISTORIAL,
    ): SerieDemanda {
        $series = $this->obtener($semanas, $productoId, $depositoId);

        return $series["{$productoId}-{$depositoId}"]
            ?? new SerieDemanda($productoId, $depositoId, []);
    }

    /**
     * Ventana de análisis: termina en la última semana COMPLETA
     * (la semana en curso se excluye porque sus ventas están incompletas).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} Lunes inicial y lunes final.
     */
    public function ventana(int $semanas, ?CarbonImmutable $referencia = null): array
    {
        $ultimoLunes = ($referencia ?? CarbonImmutable::today())
            ->startOfWeek(CarbonInterface::MONDAY)
            ->subWeek();

        return [$ultimoLunes->subWeeks(max(1, $semanas) - 1), $ultimoLunes];
    }

    /** Ventas agrupadas por producto, depósito principal y semana. */
    private function consultar(
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        ?int $productoId,
        ?int $depositoId,
    ): Collection {
        $semana = "date_trunc('week', vc.ven_fchaem)";

        return DB::table('venta_detalle as vd')
            ->join('venta_cab as vc', 'vc.ven_id', '=', 'vd.vnd_venid')
            ->join('producto as pr', 'pr.prd_id', '=', 'vd.vnd_prdid')
            // Toda venta descuenta del depósito principal de su sucursal.
            ->join('deposito as dp', function ($j) {
                $j->on('dp.dpo_sucid', '=', 'vc.ven_sucid')
                  ->where('dp.dpo_princi', 1)
                  ->where('dp.dpo_estado', 1);
            })
            ->where('vc.ven_estado', '<>', self::VENTA_ANULADA)
            ->whereBetween('vc.ven_fchaem', [$desde->toDateString(), $hasta->addDays(6)->toDateString()])
            ->where('pr.prd_afestk', 1)
            ->where('pr.prd_estado', 1)
            ->when($productoId, fn ($q) => $q->where('vd.vnd_prdid', $productoId))
            ->when($depositoId, fn ($q) => $q->where('dp.dpo_id', $depositoId))
            ->selectRaw("vd.vnd_prdid AS prd_id,
                         dp.dpo_id    AS dep_id,
                         to_char({$semana}, 'YYYY-MM-DD') AS semana,
                         SUM(vd.vnd_cantidad) AS unidades")
            ->groupByRaw("vd.vnd_prdid, dp.dpo_id, {$semana}")
            ->havingRaw('SUM(vd.vnd_cantidad) > 0')
            ->get();
    }

    /** @return string[] Lunes de cada semana de la ventana (Y-m-d). */
    private function calendario(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $semanas = [];
        for ($s = $desde; $s->lte($hasta); $s = $s->addWeek()) {
            $semanas[] = $s->toDateString();
        }

        return $semanas;
    }

    /**
     * Rellena con 0 las semanas sin venta y descarta los ceros previos
     * a la primera venta (el producto aún no se comercializaba).
     *
     * @param  string[]             $calendario
     * @param  array<string, float> $ventas
     * @return array<string, float>
     */
    private function completar(array $calendario, array $ventas): array
    {
        $serie     = [];
        $iniciada  = false;

        foreach ($calendario as $semana) {
            $valor = $ventas[$semana] ?? 0.0;

            if (! $iniciada && $valor <= 0) {
                continue;
            }

            $iniciada       = true;
            $serie[$semana] = $valor;
        }

        return $serie;
    }
}