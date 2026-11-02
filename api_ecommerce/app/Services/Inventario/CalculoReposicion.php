<?php
// app/Services/Inventario/CalculoReposicion.php
namespace App\Services\Inventario;

use App\Models\Inventario\AlertaStock;

/**
 * Cálculo puro de reposición (sin base de datos, testeable).
 *
 * Política de revisión periódica (R, S): cada R semanas se revisa el stock
 * y se pide hasta un nivel objetivo S que cubra el lead time más el período
 * de revisión, más el stock de seguridad.
 *
 *   SS  = Z · σ · √(LT)                  stock de seguridad
 *   ROP = d · LT + SS                    punto de reorden
 *   S   = d · (LT + R) + SS              nivel objetivo
 *   Sugerido = ⌈S − disponible⌉          (solo si disponible ≤ ROP)
 *
 * d y σ en unidades por semana; LT y R en semanas.
 */
final class CalculoReposicion
{
    /**
     * @return array{tipo: ?string, ss: float, rop: float, objetivo: float,
     *               sugerido: float, cobertura_dias: ?float}
     */
    public static function calcular(
        float $disponible,
        float $demandaSemanal,
        float $sigmaSemanal,
        int $leadTimeDias,
        float $z,
        int $revisionSemanas,
        int $coberturaMaxSemanas,
    ): array {
        $lt = $leadTimeDias / 7;

        $ss       = $z * $sigmaSemanal * sqrt($lt);
        $rop      = $demandaSemanal * $lt + $ss;
        $objetivo = $demandaSemanal * ($lt + $revisionSemanas) + $ss;

        $cobertura = $demandaSemanal > 0 ? $disponible / ($demandaSemanal / 7) : null;

        $tipo = match (true) {
            $demandaSemanal <= 0                                       => null,
            $disponible <= 0                                           => AlertaStock::TIPO_AGOTADO,
            $disponible <= $ss                                         => AlertaStock::TIPO_CRITICO,
            $disponible <= $rop                                        => AlertaStock::TIPO_REPONER,
            $cobertura !== null && $cobertura > $coberturaMaxSemanas * 7 => AlertaStock::TIPO_SOBRESTOCK,
            default                                                    => null,
        };

        $repone   = in_array($tipo, [AlertaStock::TIPO_AGOTADO, AlertaStock::TIPO_CRITICO, AlertaStock::TIPO_REPONER], true);
        $sugerido = $repone ? max(0.0, ceil($objetivo - max(0.0, $disponible))) : 0.0;

        return [
            'tipo'           => $tipo,
            'ss'             => round($ss, 4),
            'rop'            => round($rop, 4),
            'objetivo'       => round($objetivo, 4),
            'sugerido'       => $sugerido,
            'cobertura_dias' => $cobertura !== null ? round($cobertura, 2) : null,
        ];
    }

    /**
     * Factor Z de la normal estándar para un nivel de servicio (%).
     * Aproximación racional de Abramowitz y Stegun (26.2.23), error < 4.5e-4.
     */
    public static function factorZ(int $nivelServicio): float
    {
        $p = min(max($nivelServicio, 50), 99.9) / 100;
        if ($p <= 0.5) {
            return 0.0;
        }

        $t = sqrt(-2 * log(1 - $p));
        $z = $t - (2.515517 + 0.802853 * $t + 0.010328 * $t ** 2)
                / (1 + 1.432788 * $t + 0.189269 * $t ** 2 + 0.001308 * $t ** 3);

        return round($z, 4);
    }
}