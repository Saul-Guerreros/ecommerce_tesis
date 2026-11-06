<?php
// app/Services/Inventario/EvaluadorModelos.php
namespace App\Services\Inventario;

use App\Services\Inventario\Modelos\CrostonModelo;
use App\Services\Inventario\Modelos\HoltModelo;
use App\Services\Inventario\Modelos\ModeloPronostico;
use App\Services\Inventario\Modelos\PromedioMovilModelo;
use App\Services\Inventario\Modelos\SbaModelo;
use App\Services\Inventario\Modelos\SesModelo;

/**
 * Backtesting (validación fuera de muestra) y selección del mejor modelo.
 *
 * Origen móvil (rolling origin): sobre las últimas SEMANAS_PRUEBA semanas se
 * simulan varios "momentos de decisión". En cada uno el modelo se entrena solo
 * con los datos anteriores y pronostica HORIZONTE_PRUEBA semanas, que se
 * comparan con lo realmente vendido. Es más robusto que un único corte.
 *
 * Criterio de selección: menor RMSE. El MAE se minimiza con la mediana, que
 * en demanda intermitente es 0, y premiaría pronosticar "nada"; el RMSE se
 * minimiza con la media, que es lo que necesita el cálculo de reposición.
 * En empate gana el modelo más simple (orden de modelos()).
 */
class EvaluadorModelos
{
    public const SEMANAS_PRUEBA    = 8;
    public const HORIZONTE_PRUEBA  = 4;
    public const MIN_ENTRENAMIENTO = 8;

    /** @return ModeloPronostico[] Del más simple al más complejo. */
    public function modelos(): array
    {
        return [
            new PromedioMovilModelo(4),
            new SesModelo(),
            new HoltModelo(),
            new CrostonModelo(),
            new SbaModelo(),
        ];
    }

    public function puedeEvaluar(SerieDemanda $serie): bool
    {
        return $serie->tieneHistorialSuficiente()
            && $serie->longitud() >= self::SEMANAS_PRUEBA + self::MIN_ENTRENAMIENTO;
    }

    /**
     * @return array<string, array{orden: int, rmse: float, mae: float, wape: ?float}>
     *         Resultado por nombre de modelo, ordenado del mejor al peor.
     */
    public function evaluar(SerieDemanda $serie): array
    {
        $datos = $serie->datos();
        $n     = count($datos);

        $resultados = [];
        foreach ($this->modelos() as $orden => $modelo) {
            $real = [];
            $previsto = [];

            for ($origen = $n - self::SEMANAS_PRUEBA; $origen <= $n - self::HORIZONTE_PRUEBA; $origen++) {
                $pronostico = $modelo->pronosticar(array_slice($datos, 0, $origen), self::HORIZONTE_PRUEBA);

                for ($h = 0; $h < self::HORIZONTE_PRUEBA; $h++) {
                    $real[]     = $datos[$origen + $h];
                    $previsto[] = $pronostico[$h];
                }
            }

            $resultados[$modelo->nombre()] = [
                'orden' => $orden,
                'rmse'  => Metricas::rmse($real, $previsto),
                'mae'   => Metricas::mae($real, $previsto),
                'wape'  => Metricas::wape($real, $previsto),
            ];
        }

        uasort($resultados, fn ($a, $b) => [$a['rmse'], $a['orden']] <=> [$b['rmse'], $b['orden']]);

        return $resultados;
    }

    /**
     * Modelo a usar cuando la serie es demasiado corta para backtesting:
     * regla basada en la clasificación de Syntetos-Boylan.
     */
    public function modeloPorClasificacion(SerieDemanda $serie): ModeloPronostico
    {
        return match ($serie->clasificacion()) {
            SerieDemanda::CLASE_INTERMITENTE => new SbaModelo(),
            SerieDemanda::CLASE_REGULAR      => new SesModelo(),
            default                          => new PromedioMovilModelo(SerieDemanda::MIN_SEMANAS),
        };
    }

    public function modeloPorNombre(string $nombre): ModeloPronostico
    {
        foreach ($this->modelos() as $modelo) {
            if ($modelo->nombre() === $nombre) {
                return $modelo;
            }
        }

        throw new \InvalidArgumentException("Modelo desconocido: {$nombre}");
    }
}