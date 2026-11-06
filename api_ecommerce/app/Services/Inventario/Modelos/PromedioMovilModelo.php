<?php
// app/Services/Inventario/Modelos/PromedioMovilModelo.php
namespace App\Services\Inventario\Modelos;

/**
 * Línea base: promedio de las últimas k semanas, proyectado plano.
 * Sirve de referencia: un modelo "inteligente" debe superarlo.
 */
class PromedioMovilModelo implements ModeloPronostico
{
    public function __construct(private readonly int $ventana = 4)
    {
    }

    public function nombre(): string
    {
        return 'promedio_movil';
    }

    public function pronosticar(array $serie, int $horizonte): array
    {
        $ultimos = array_slice($serie, -$this->ventana);
        $media   = $ultimos ? array_sum($ultimos) / count($ultimos) : 0.0;

        return array_fill(0, $horizonte, round($media, 4));
    }

    public function parametros(): array
    {
        return ['k' => $this->ventana];
    }
}