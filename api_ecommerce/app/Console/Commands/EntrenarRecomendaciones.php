<?php

namespace App\Console\Commands;

use App\Services\Recomendacion\EntrenadorSimilitudService;
use App\Services\Recomendacion\RecomendacionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 *   php artisan recomendaciones:entrenar
 */
class EntrenarRecomendaciones extends Command
{
    protected $signature = 'recomendaciones:entrenar';

    protected $description = 'Recalcula la matriz de similitud entre productos (Filtrado Colaborativo Ítem-Ítem)';

    public function handle(EntrenadorSimilitudService $entrenador): int
    {
        $inicio = microtime(true);

        // Límite exclusivo = mañana: incluye las ventas y vistas de hoy.
        $matriz = $entrenador->interacciones(CarbonImmutable::tomorrow());
        $vecinos = $entrenador->similitudes($matriz);
        $filas = $entrenador->guardar($vecinos);

        Cache::forget(RecomendacionService::CACHE_TENDENCIAS);

        $this->info(sprintf(
            'Usuarios: %d | Productos con vecinos: %d | Pares guardados: %d | %.2f s',
            count($matriz), count($vecinos), $filas, microtime(true) - $inicio
        ));

        return self::SUCCESS;
    }
}