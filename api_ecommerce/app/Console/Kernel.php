<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('servicio:vencer-presupuestos')->dailyAt('00:10')->withoutOverlapping();
        $schedule->command('facturacion:reintentar')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('facturacion:reintentar-nc')->everyTenMinutes()->withoutOverlapping();

        // Reservas vencidas: pago no declarado, comprobante rechazado sin
        // reemplazo y cheque no entregado en sus 48 horas.
        //
        // Cada 15 minutos y no una vez al día porque el stock liberado
        // vuelve a estar disponible para el resto de la tienda: una demora
        // larga puede ser una venta perdida. withoutOverlapping evita que
        // dos corridas procesen el mismo pedido si una se atrasa.
        //
        // La salida se guarda en un archivo: es la única forma de saber
        // después qué cancelaron las corridas automáticas, ya que nadie
        $schedule->command('pedidos:expirar')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/pedidos-expirar.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}