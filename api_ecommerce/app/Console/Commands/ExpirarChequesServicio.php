<?php

namespace App\Console\Commands;

use App\Models\Servicio\ServicioCheque;
use App\Services\Servicio\ChequeServicioGestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cheques del servicio no entregados en 48 h. No cancela nada: marca el
 * cheque NO_ENTREGADO, rechaza ese pago y reabre el saldo.
 *
 *   php artisan servicio:expirar-cheques [--dry-run]
 */
class ExpirarChequesServicio extends Command
{
    protected $signature = 'servicio:expirar-cheques
                            {--dry-run : Listar los cheques vencidos sin tocarlos}
                            {--limite=200 : Máximo de cheques a procesar}';

    protected $description = 'Marca como no entregados los cheques del servicio técnico vencidos y reabre el saldo';

    public function handle(ChequeServicioGestionService $cheques): int
    {
        $simulacion = (bool) $this->option('dry-run');

        $vencidos = ServicioCheque::vencidos()
            ->with('pago.solicitud:sse_id,sse_numero')
            ->orderBy('sch_fevcto')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();

        if ($vencidos->isEmpty()) {
            $this->info('No hay cheques de servicio vencidos.');
            return self::SUCCESS;
        }

        $procesados = 0;

        foreach ($vencidos as $cheque) {
            $this->line(sprintf('  %s · pago %d · venció %s',
                $cheque->pago?->solicitud?->sse_numero, $cheque->pago?->spa_orden, $cheque->sch_fevcto?->diffForHumans()));

            if ($simulacion) {
                continue;
            }

            try {
                $procesados += $cheques->marcarNoEntregado($cheque) ? 1 : 0;
            } catch (\Throwable $e) {
                $this->error("    No se pudo procesar el cheque {$cheque->sch_id}: {$e->getMessage()}");
                Log::error('servicio:expirar-cheques falló', ['sch_id' => $cheque->sch_id, 'error' => $e->getMessage()]);
            }
        }

        if (!$simulacion) {
            $this->info("Listo: {$procesados} cheque(s) marcados como no entregados.");
        }

        return self::SUCCESS;
    }
}