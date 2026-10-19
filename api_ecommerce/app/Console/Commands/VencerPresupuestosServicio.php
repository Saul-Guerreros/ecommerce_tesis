<?php

namespace App\Console\Commands;

use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\PresupuestoServicio;
use App\Services\Servicio\PresupuestoService;
use Illuminate\Console\Command;

/**
 * FASE 4 - Presupuestos ENVIADOS sin respuesta pasada la fecha de vencimiento:
 * pasan a VENCIDO y el equipo queda LISTO para devolver (mismo camino que
 * el rechazo). Programado a diario en App\Console\Kernel.
 *
 *   php artisan servicio:vencer-presupuestos
 */
class VencerPresupuestosServicio extends Command
{
    protected $signature = 'servicio:vencer-presupuestos';
    protected $description = 'Marca como VENCIDOS los presupuestos de servicio técnico sin respuesta';

    public function handle(PresupuestoService $servicio): int
    {
        $vencidos = PresupuestoServicio::whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::ENVIADO))
            ->whereDate('pse_fecven', '<', today())
            ->with('estado')
            ->get();

        foreach ($vencidos as $p) {
            try {
                $servicio->vencer($p);
                $this->line("Vencido: {$p->pse_numero}");
            } catch (\Throwable $e) {
                // Un error en uno no detiene al resto.
                report($e);
                $this->error("Error en {$p->pse_numero}: {$e->getMessage()}");
            }
        }

        $this->info("Presupuestos vencidos: {$vencidos->count()}");

        return self::SUCCESS;
    }
}
