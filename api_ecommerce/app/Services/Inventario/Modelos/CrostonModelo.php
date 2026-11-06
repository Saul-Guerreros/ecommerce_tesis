<?php
// app/Services/Inventario/Modelos/CrostonModelo.php
namespace App\Services\Inventario\Modelos;

/**
 * Método de Croston (1972) para demanda intermitente.
 * Suaviza por separado, y solo en las semanas con venta:
 *   z = tamaño de la venta     p = intervalo entre ventas (semanas)
 * Pronóstico por semana = z / p.
 */
class CrostonModelo extends ModeloSuavizado
{
    public function nombre(): string
    {
        return 'croston';
    }

    protected function grilla(): array
    {
        return array_map(fn ($a) => ['alfa' => $a], [0.05, 0.1, 0.15, 0.2, 0.3]);
    }

    /** Factor de corrección del pronóstico (1 en Croston; SBA lo redefine). */
    protected function correccion(float $alfa): float
    {
        return 1.0;
    }

    protected function ajustar(array $serie, array $p): array
    {
        $alfa = $p['alfa'];
        $k    = $this->correccion($alfa);

        // Inicialización con los promedios de la serie: tamaño medio de venta e
        // intervalo medio entre ventas (evita el sesgo de arrancar con un solo dato).
        $ventas    = array_values(array_filter($serie, fn ($v) => $v > 0));
        if ($ventas === []) {
            return ['ajuste' => [], 'prever' => fn (int $h) => 0.0];
        }
        $z         = array_sum($ventas) / count($ventas);
        $intervalo = count($serie) / count($ventas);
        $primera   = array_key_first(array_filter($serie, fn ($v) => $v > 0));
        $q         = 1; // semanas desde la última venta

        $ajuste = [];
        for ($t = $primera + 1, $n = count($serie); $t < $n; $t++) {
            $ajuste[$t] = $intervalo > 0 ? $k * $z / $intervalo : 0.0;

            if ($serie[$t] > 0) {
                $z         = $alfa * $serie[$t] + (1 - $alfa) * $z;
                $intervalo = $alfa * $q + (1 - $alfa) * $intervalo;
                $q = 1;
            } else {
                $q++;
            }
        }

        $prevision = $intervalo > 0 ? $k * $z / $intervalo : 0.0;

        return ['ajuste' => $ajuste, 'prever' => fn (int $h) => $prevision];
    }
}