<?php
// app/Console/Commands/VerSerieDemanda.php
namespace App\Console\Commands;

use App\Services\Inventario\SerieDemanda;
use App\Services\Inventario\SerieDemandaService;
use Illuminate\Console\Command;

/**
 * Verificación de la Fase 2: muestra las series de demanda semanales.
 *
 *   php artisan inventario:serie                 -> resumen general (top 15)
 *   php artisan inventario:serie 12              -> detalle del producto 12
 *   php artisan inventario:serie 12 --deposito=1 --semanas=26
 */
class VerSerieDemanda extends Command
{
    protected $signature = 'inventario:serie
        {producto? : ID del producto (sin él muestra un resumen general)}
        {--deposito= : ID del depósito}
        {--semanas=52 : Semanas de historial}';

    protected $description = 'Muestra la serie semanal de demanda por producto y depósito';

    public function handle(SerieDemandaService $servicio): int
    {
        $semanas    = max(4, (int) $this->option('semanas'));
        $productoId = $this->argument('producto') ? (int) $this->argument('producto') : null;
        $depositoId = $this->option('deposito') ? (int) $this->option('deposito') : null;

        [$desde, $hasta] = $servicio->ventana($semanas);
        $this->info("Ventana: {$desde->toDateString()} al {$hasta->addDays(6)->toDateString()} ({$semanas} semanas)");

        $series = $servicio->obtener($semanas, $productoId, $depositoId);

        if (! $series) {
            $this->warn('No hay ventas en la ventana analizada.');
            return self::SUCCESS;
        }

        $productoId ? $this->detalle($series) : $this->resumen($series);

        return self::SUCCESS;
    }

    /** @param array<string, SerieDemanda> $series */
    private function detalle(array $series): void
    {
        foreach ($series as $s) {
            $this->newLine();
            $this->line("<comment>Producto {$s->productoId} · Depósito {$s->depositoId}</comment>");
            $this->table(
                ['Semana (lunes)', 'Unidades'],
                array_map(fn ($sem, $v) => [$sem, $v], $s->semanas(), $s->datos())
            );
            $this->line(sprintf(
                'Semanas: %d | Con venta: %d | Ceros: %s%% | ADI: %s | Total: %s | %s',
                $s->longitud(), $s->semanasConVenta(), $s->porcentajeCeros(),
                $s->adi(), $s->total(), strtoupper($s->clasificacion())
            ));
        }
    }

    /** @param array<string, SerieDemanda> $series */
    private function resumen(array $series): void
    {
        $filas = collect($series)
            ->sortByDesc(fn (SerieDemanda $s) => $s->total())
            ->take(15)
            ->map(fn (SerieDemanda $s) => [
                $s->productoId, $s->depositoId, $s->longitud(), $s->semanasConVenta(),
                $s->porcentajeCeros() . '%', $s->adi(), $s->total(), $s->clasificacion(),
            ])
            ->values()
            ->all();

        $this->table(['Producto', 'Depósito', 'Semanas', 'Con venta', 'Ceros', 'ADI', 'Total', 'Tipo'], $filas);

        $conteo = collect($series)->countBy(fn (SerieDemanda $s) => $s->clasificacion());
        $total  = count($series);

        $this->info(sprintf(
            '%d series · regulares: %d · intermitentes: %d · historial insuficiente: %d',
            $total,
            $conteo[SerieDemanda::CLASE_REGULAR] ?? 0,
            $conteo[SerieDemanda::CLASE_INTERMITENTE] ?? 0,
            $conteo[SerieDemanda::CLASE_INSUFICIENTE] ?? 0,
        ));
    }
}