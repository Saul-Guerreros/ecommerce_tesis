<?php
// app/Services/Inventario/Modelos/ModeloSuavizado.php
namespace App\Services\Inventario\Modelos;

/**
 * Base de los modelos de suavizado exponencial (SES, Holt, Croston, SBA).
 *
 * Template Method: cada modelo define su grilla de parámetros y cómo
 * recorre la serie; esta clase elige automáticamente los parámetros que
 * minimizan el error cuadrático medio de los pronósticos a un paso
 * dentro de los datos de entrenamiento.
 */
abstract class ModeloSuavizado implements ModeloPronostico
{
    private array $parametros = [];

    /** @return array<int, array<string, float>> Combinaciones de parámetros a probar. */
    abstract protected function grilla(): array;

    /**
     * Recorre la serie con los parámetros dados.
     *
     * @return array{ajuste: array<int, float>, prever: callable(int): float}
     *         ajuste[t] = pronóstico de la semana t hecho al cierre de t-1.
     *         prever(h) = pronóstico h semanas después del último dato.
     */
    abstract protected function ajustar(array $serie, array $p): array;

    public function pronosticar(array $serie, int $horizonte): array
    {
        if ($serie === []) {
            return array_fill(0, $horizonte, 0.0);
        }

        $mejor = null;
        foreach ($this->grilla() as $p) {
            $resultado = $this->ajustar($serie, $p);
            $ecm = $this->errorCuadraticoMedio($serie, $resultado['ajuste']);

            if ($mejor === null || $ecm < $mejor['ecm']) {
                $mejor = ['ecm' => $ecm, 'p' => $p, 'prever' => $resultado['prever']];
            }
        }

        $this->parametros = $mejor['p'];

        $pronostico = [];
        for ($h = 1; $h <= $horizonte; $h++) {
            $pronostico[] = max(0.0, round(($mejor['prever'])($h), 4));
        }

        return $pronostico;
    }

    public function parametros(): array
    {
        return $this->parametros;
    }

    /** @param array<int, float> $ajuste */
    private function errorCuadraticoMedio(array $serie, array $ajuste): float
    {
        $suma = 0.0;
        $n = 0;
        foreach ($ajuste as $t => $previsto) {
            $suma += ($serie[$t] - $previsto) ** 2;
            $n++;
        }

        return $n > 0 ? $suma / $n : INF;
    }
}