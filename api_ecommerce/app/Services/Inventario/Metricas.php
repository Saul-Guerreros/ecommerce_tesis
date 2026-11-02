<?php
// app/Services/Inventario/Metricas.php
namespace App\Services\Inventario;

/** Métricas de error de pronóstico. */
final class Metricas
{
    /** Raíz del error cuadrático medio: penaliza más los errores grandes. */
    public static function rmse(array $real, array $previsto): float
    {
        $n = count($real);
        if ($n === 0) {
            return 0.0;
        }

        $suma = 0.0;
        foreach ($real as $i => $r) {
            $suma += ($r - ($previsto[$i] ?? 0.0)) ** 2;
        }

        return round(sqrt($suma / $n), 4);
    }

    /** Error absoluto medio: unidades/semana que se equivoca en promedio. */
    public static function mae(array $real, array $previsto): float
    {
        $n = count($real);
        if ($n === 0) {
            return 0.0;
        }

        $suma = 0.0;
        foreach ($real as $i => $r) {
            $suma += abs($r - ($previsto[$i] ?? 0.0));
        }

        return round($suma / $n, 4);
    }

    /**
     * Error porcentual absoluto ponderado: Σ|error| / Σ real.
     * A diferencia del MAPE, no se indefine en semanas con venta 0.
     * Devuelve null si no hubo ventas en todo el período de prueba.
     */
    public static function wape(array $real, array $previsto): ?float
    {
        $totalReal = array_sum($real);
        if ($totalReal <= 0) {
            return null;
        }

        $suma = 0.0;
        foreach ($real as $i => $r) {
            $suma += abs($r - ($previsto[$i] ?? 0.0));
        }

        return round($suma / $totalReal, 4);
    }
}