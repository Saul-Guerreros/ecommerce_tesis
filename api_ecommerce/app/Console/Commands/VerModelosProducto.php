<?php
// app/Console/Commands/VerModelosProducto.php
namespace App\Console\Commands;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Services\Inventario\EvaluadorModelos;
use App\Services\Inventario\SerieDemandaService;
use Illuminate\Console\Command;

/**
 * Compara los 5 modelos para un producto (no guarda nada).
 *   php artisan inventario:modelos 165
 *   php artisan inventario:modelos 165 --deposito=1
 */
class VerModelosProducto extends Command
{
    protected $signature = 'inventario:modelos
        {producto : ID del producto}
        {--deposito= : ID del depósito (por defecto, todos los que tengan ventas)}';

    protected $description = 'Muestra el backtesting de los 5 modelos y el pronóstico para un producto';

    public function handle(SerieDemandaService $series, EvaluadorModelos $evaluador): int
    {
        $productoId = (int) $this->argument('producto');
        $depositoId = $this->option('deposito') ? (int) $this->option('deposito') : null;
        $horizonte  = (int) (ConfiguracionTienda::actual()->ctn_horizonte ?: 4);

        $resultado = $series->obtener(productoId: $productoId, depositoId: $depositoId);
        if (! $resultado) {
            $this->warn('El producto no tiene ventas en la ventana analizada.');
            return self::SUCCESS;
        }

        foreach ($resultado as $serie) {
            $this->newLine();
            $this->line(sprintf(
                '<comment>Producto %d · Depósito %d · %d semanas · %s · ADI %s</comment>',
                $serie->productoId, $serie->depositoId, $serie->longitud(),
                strtoupper($serie->clasificacion()), $serie->adi()
            ));
            $this->line('Últimas 8 semanas reales: ' . implode(', ', array_slice($serie->datos(), -8)));

            if (! $evaluador->puedeEvaluar($serie)) {
                $modelo = $evaluador->modeloPorClasificacion($serie);
                $this->warn("Historial corto para backtesting: se usa {$modelo->nombre()} por clasificación.");
                continue;
            }

            $filas = [];
            foreach ($evaluador->evaluar($serie) as $nombre => $r) {
                $modelo     = $evaluador->modeloPorNombre($nombre);
                $pronostico = $modelo->pronosticar($serie->datos(), $horizonte);

                $filas[] = [
                    $filas === [] ? "★ {$nombre}" : $nombre,
                    $r['rmse'],
                    $r['mae'],
                    $r['wape'] !== null ? round($r['wape'] * 100, 1) . '%' : '-',
                    implode(' / ', array_map(fn ($v) => round($v, 2), $pronostico)),
                    json_encode($modelo->parametros()),
                ];
            }

            $this->table(['Modelo', 'RMSE', 'MAE', 'WAPE', "Pronóstico {$horizonte} sem.", 'Parámetros'], $filas);
        }

        return self::SUCCESS;
    }
}