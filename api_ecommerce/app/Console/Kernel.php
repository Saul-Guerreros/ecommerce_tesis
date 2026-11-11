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
        // Servicio técnico
        $schedule->command('servicio:vencer-presupuestos')->dailyAt('00:10')->withoutOverlapping();
        $schedule->command('servicio:clasificar-pendientes')->everyFifteenMinutes()->withoutOverlapping();

        // Facturación
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
        // está mirando la consola cuando se ejecutan.
        $schedule->command('pedidos:expirar')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/pedidos-expirar.log'));

        // Ofertas de crédito vencidas: el cliente no aceptó dentro de su
        // plazo y la mercadería sigue apartada.
        //
        // Una vez al día y no cada 15 minutos como los pedidos: la
        // vigencia del crédito se mide en días, así que liberar a las
        // 00:20 del día siguiente al vencimiento es suficiente. Correrlo
        // más seguido solo agregaría consultas sin ganar disponibilidad.
        $schedule->command('creditos:expirar')
            ->dailyAt('00:20')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/creditos-expirar.log'));    

        // Mantenimiento
        $schedule->command('actividad:purgar')->monthlyOn(1, '03:00')->withoutOverlapping();

        // Gestión inteligente de inventario
        $schedule->command('inventario:pronosticar')->weeklyOn(1, '02:00')->withoutOverlapping();
        // Alertas: cada hora de 07:00 a 20:00 (horario comercial) para reflejar las ventas del día.
        $schedule->command('inventario:alertas')->hourly()->between('07:00', '20:00')->withoutOverlapping();
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