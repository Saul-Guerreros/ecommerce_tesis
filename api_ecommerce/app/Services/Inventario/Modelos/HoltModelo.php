<?php
// app/Services/Inventario/Modelos/HoltModelo.php
namespace App\Services\Inventario\Modelos;

/**
 * Suavizado exponencial doble de Holt (1957): nivel + tendencia.
 *   nivel_t     = α·y_t + (1-α)·(nivel_{t-1} + tend_{t-1})
 *   tend_t      = β·(nivel_t - nivel_{t-1}) + (1-β)·tend_{t-1}
 *   pronóstico  = nivel + h·tend   (recortado a 0 si da negativo)
 * Adecuado para demanda regular con crecimiento o caída sostenida.
 */
class HoltModelo extends ModeloSuavizado
{
    public function nombre(): string
    {
        return 'holt';
    }

    protected function grilla(): array
    {
        $grilla = [];
        foreach ([0.1, 0.2, 0.3, 0.5] as $alfa) {
            foreach ([0.05, 0.1, 0.2] as $beta) {
                $grilla[] = ['alfa' => $alfa, 'beta' => $beta];
            }
        }

        return $grilla;
    }

    protected function ajustar(array $serie, array $p): array
    {
        $n = count($serie);
        if ($n < 2) {
            return ['ajuste' => [], 'prever' => fn (int $h) => $serie[0]];
        }

        $nivel  = $serie[0];
        $tend   = $serie[1] - $serie[0];
        $ajuste = [];

        for ($t = 1; $t < $n; $t++) {
            $ajuste[$t] = $nivel + $tend;
            $anterior   = $nivel;
            $nivel = $p['alfa'] * $serie[$t] + (1 - $p['alfa']) * ($nivel + $tend);
            $tend  = $p['beta'] * ($nivel - $anterior) + (1 - $p['beta']) * $tend;
        }

        return ['ajuste' => $ajuste, 'prever' => fn (int $h) => $nivel + $h * $tend];
    }
}