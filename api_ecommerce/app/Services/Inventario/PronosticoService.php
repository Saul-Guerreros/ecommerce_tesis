<?php
// app/Services/Inventario/PronosticoService.php
namespace App\Services\Inventario;

use App\Models\Configuracion\ConfiguracionTienda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta el pronóstico de todo el catálogo:
 *   serie de demanda -> backtesting de los 5 modelos -> elige el mejor
 *   -> reentrena con TODA la serie -> pronostica el horizonte -> guarda.
 *
 * prediccion_demanda y metrica_modelo guardan el ESTADO ACTUAL: cada
 * ejecución las reemplaza completas dentro de una transacción.
 */
class PronosticoService
{
    public function __construct(
        private readonly SerieDemandaService $series,
        private readonly EvaluadorModelos $evaluador,
    ) {
    }

    /**
     * @return array{series: int, horizonte: int, por_modelo: array<string, int>,
     *               por_clase: array<string, int>, sin_backtest: int,
     *               error_por_modelo: array<string, array{rmse: float, mae: float, wape: ?float}>}
     */
    public function ejecutar(): array
    {
        $horizonte = $this->horizonte();
        [, $ultimoLunes] = $this->series->ventana(SerieDemandaService::SEMANAS_HISTORIAL);
        $ahora = CarbonImmutable::now();

        $predicciones = [];
        $metricas     = [];
        $resumen      = ['por_modelo' => [], 'por_clase' => [], 'sin_backtest' => 0, 'errores' => []];

        foreach ($this->series->obtener() as $serie) {
            $resumen['por_clase'][$serie->clasificacion()] = ($resumen['por_clase'][$serie->clasificacion()] ?? 0) + 1;

            if ($this->evaluador->puedeEvaluar($serie)) {
                $resultados = $this->evaluador->evaluar($serie);
                $elegido    = array_key_first($resultados);

                foreach ($resultados as $nombre => $r) {
                    $metricas[] = [
                        'mmo_prdid'   => $serie->productoId,
                        'mmo_depid'   => $serie->depositoId,
                        'mmo_modelo'  => $nombre,
                        'mmo_rmse'    => $r['rmse'],
                        'mmo_mae'     => $r['mae'],
                        'mmo_wape'    => $r['wape'],
                        'mmo_semprb'  => EvaluadorModelos::SEMANAS_PRUEBA,
                        'mmo_elegido' => $nombre === $elegido,
                        'mmo_creado'  => $ahora,
                    ];
                    $resumen['errores'][$nombre]['rmse'][] = $r['rmse'];
                    $resumen['errores'][$nombre]['mae'][]  = $r['mae'];
                    if ($r['wape'] !== null) {
                        $resumen['errores'][$nombre]['wape'][] = $r['wape'];
                    }
                }

                $modelo   = $this->evaluador->modeloPorNombre($elegido);
                $etiqueta = $elegido;
            } else {
                $modelo   = $this->evaluador->modeloPorClasificacion($serie);
                $etiqueta = $modelo->nombre();
                $resumen['sin_backtest']++;
            }

            // Historial insuficiente: se completa con ceros hasta MIN_SEMANAS para no
            // extrapolar una venta aislada como si fuera la demanda de cada semana.
            $datos = $serie->clasificacion() === SerieDemanda::CLASE_INSUFICIENTE
                ? array_pad($serie->datos(), -SerieDemanda::MIN_SEMANAS, 0.0)
                : $serie->datos();

            // Reentrenar con toda la serie y pronosticar el horizonte.
            foreach ($modelo->pronosticar($datos, $horizonte) as $i => $cantidad) {
                $predicciones[] = [
                    'pdd_prdid'   => $serie->productoId,
                    'pdd_depid'   => $serie->depositoId,
                    'pdd_periodo' => $ultimoLunes->addWeeks($i + 1)->toDateString(),
                    'pdd_cantid'  => $cantidad,
                    'pdd_modelo'  => $etiqueta,
                    'pdd_creado'  => $ahora,
                ];
            }

            $resumen['por_modelo'][$etiqueta] = ($resumen['por_modelo'][$etiqueta] ?? 0) + 1;
        }

        $this->guardar($predicciones, $metricas);

        return [
            'series'           => array_sum($resumen['por_clase']),
            'horizonte'        => $horizonte,
            'por_modelo'       => $resumen['por_modelo'],
            'por_clase'        => $resumen['por_clase'],
            'sin_backtest'     => $resumen['sin_backtest'],
            'error_por_modelo' => array_map(fn ($e) => [
                'rmse' => round(array_sum($e['rmse']) / count($e['rmse']), 4),
                'mae'  => round(array_sum($e['mae']) / count($e['mae']), 4),
                'wape' => ! empty($e['wape']) ? round(array_sum($e['wape']) / count($e['wape']), 4) : null,
            ], $resumen['errores']),
        ];
    }

    private function guardar(array $predicciones, array $metricas): void
    {
        DB::transaction(function () use ($predicciones, $metricas) {
            DB::table('prediccion_demanda')->delete();
            DB::table('metrica_modelo')->delete();

            foreach (array_chunk($predicciones, 500) as $lote) {
                DB::table('prediccion_demanda')->insert($lote);
            }
            foreach (array_chunk($metricas, 500) as $lote) {
                DB::table('metrica_modelo')->insert($lote);
            }
        });
    }

    private function horizonte(): int
    {
        return (int) (ConfiguracionTienda::actual()->ctn_horizonte ?: 4);
    }
}