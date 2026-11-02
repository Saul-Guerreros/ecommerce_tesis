<?php
// app/Console/Commands/EjecutarPronostico.php
namespace App\Console\Commands;

use App\Services\Inventario\PronosticoService;
use App\Services\Inventario\ReposicionService;
use Illuminate\Console\Command;

/**
 * Proceso completo: pronostica la demanda de todo el catálogo y luego
 * recalcula las alertas de reposición.
 *   php artisan inventario:pronosticar
 */
class EjecutarPronostico extends Command
{
    protected $signature = 'inventario:pronosticar';

    protected $description = 'Pronostica la demanda (5 modelos, selección por backtesting) y actualiza las alertas';

    public function handle(PronosticoService $pronostico, ReposicionService $reposicion): int
    {
        $inicio   = microtime(true);
        $resumen  = $pronostico->ejecutar();
        $alertas  = $reposicion->sincronizarAlertas();
        $segundos = round(microtime(true) - $inicio, 1);

        $this->info("Completado en {$segundos} s · {$resumen['series']} series · horizonte {$resumen['horizonte']} semanas");

        $this->table(['Clasificación', 'Series'], collect($resumen['por_clase'])
            ->map(fn ($n, $clase) => [$clase, $n])->values()->all());

        $this->table(['Modelo elegido', 'Series'], collect($resumen['por_modelo'])
            ->sortDesc()->map(fn ($n, $modelo) => [$modelo, $n])->values()->all());

        $this->line("Series sin backtesting (historial corto, modelo por clasificación): {$resumen['sin_backtest']}");

        $this->table(['Modelo', 'RMSE prom.', 'MAE prom.', 'WAPE prom.'], collect($resumen['error_por_modelo'])
            ->sortBy('rmse')
            ->map(fn ($e, $modelo) => [$modelo, $e['rmse'], $e['mae'], $e['wape'] !== null ? round($e['wape'] * 100, 1) . '%' : '-'])
            ->values()->all());

        $this->info("Alertas: {$alertas['creadas']} creadas · {$alertas['actualizadas']} actualizadas · {$alertas['cerradas']} cerradas automáticamente");
        $this->table(['Tipo', 'Pendientes'], collect($alertas['por_tipo'])
            ->map(fn ($n, $tipo) => [$tipo, $n])->values()->all());

        return self::SUCCESS;
    }
}