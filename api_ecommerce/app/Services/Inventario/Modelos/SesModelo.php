<?php
// app/Services/Inventario/Modelos/SesModelo.php
namespace App\Services\Inventario\Modelos;

/**
 * Suavizado exponencial simple (Brown, 1956).
 *   nivel_t = α·y_t + (1-α)·nivel_{t-1}
 * Pronóstico plano = último nivel. Adecuado para demanda regular sin tendencia.
 */
class SesModelo extends ModeloSuavizado
{
    public function nombre(): string
    {
        return 'ses';
    }

    protected function grilla(): array
    {
        return array_map(fn ($a) => ['alfa' => $a], [0.05, 0.1, 0.2, 0.3, 0.5, 0.7]);
    }

    protected function ajustar(array $serie, array $p): array
    {
        $nivel  = $serie[0];
        $ajuste = [];

        for ($t = 1, $n = count($serie); $t < $n; $t++) {
            $ajuste[$t] = $nivel;
            $nivel = $p['alfa'] * $serie[$t] + (1 - $p['alfa']) * $nivel;
        }

        return ['ajuste' => $ajuste, 'prever' => fn (int $h) => $nivel];
    }
}