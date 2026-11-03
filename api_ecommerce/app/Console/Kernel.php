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
        // $schedule->command('inspire')->hourly();
		 $schedule->command('servicio:vencer-presupuestos')->dailyAt('00:10')->withoutOverlapping();
         $schedule->command('facturacion:reintentar')->everyTenMinutes()->withoutOverlapping();
         $schedule->command('actividad:purgar')->monthlyOn(1, '03:00')->withoutOverlapping();
         $schedule->command('servicio:clasificar-pendientes')->everyFifteenMinutes()->withoutOverlapping();

        // Gestión inteligente de inventario
         $schedule->command('inventario:pronosticar')->weeklyOn(1, '02:00')->withoutOverlapping();
		 // Alertas: cada hora de 07:00 a 20:00 (horario comercial) para reflejar las ventas del día.
         $schedule->command('inventario:alertas')->hourly()->between('07:00', '20:00')->withoutOverlapping();
         $schedule->command('facturacion:reintentar-nc')->everyTenMinutes()->withoutOverlapping();
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
