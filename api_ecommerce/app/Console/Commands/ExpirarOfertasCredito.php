<?php

namespace App\Console\Commands;

use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoHistorial;
use App\Services\Credito\StockCreditoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Anula las solicitudes de crédito cuyo plazo venció y libera su stock.
 *
 * El plazo es la vigencia que el admin fija al aprobar (scr_vighas) y
 * cubre las dos etapas del cliente:
 *   - APROBADA: no aceptó la oferta a tiempo.
 *   - ACEPTADA: aceptó pero no completó el pedido a tiempo.
 * En ambos casos la solicitud pasa a ANULADA y el stock reservado vuelve
 * al depósito. Una solicitud CONVERTIDA (ya tiene pedido) no se toca: su
 * reserva pertenece al pedido.
 *
 *   php artisan creditos:expirar
 *   php artisan creditos:expirar --dry-run
 *   php artisan creditos:expirar --limite=50
 */
class ExpirarOfertasCredito extends Command
{
    protected $signature = 'creditos:expirar
                            {--dry-run : Listar las solicitudes vencidas sin anularlas}
                            {--limite=200 : Máximo de solicitudes a procesar en esta corrida}';

    protected $description = 'Anula las solicitudes de crédito con el plazo vencido y libera su stock reservado';

    private const ESTADOS_QUE_VENCEN = [EstadoSolicitud::APROBADA, EstadoSolicitud::ACEPTADA];

    public function handle(StockCreditoService $stock): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $limite     = max(1, (int) $this->option('limite'));

        $vencidas = $this->solicitudesVencidas($limite);

        if ($vencidas->isEmpty()) {
            $this->info('No hay solicitudes de crédito con el plazo vencido.');

            return self::SUCCESS;
        }

        $this->info($vencidas->count() . ' solicitud(es) vencida(s)' . ($simulacion ? ' [simulación]' : '') . ':');

        $anuladas = 0;

        foreach ($vencidas as $solicitud) {
            $this->line("  {$solicitud->scr_numero} · {$solicitud->estado?->esc_descri} · venció {$solicitud->scr_vighas->diffForHumans()}"
                . ' · Gs. ' . number_format((float) $solicitud->scr_total, 0, ',', '.')
                . ' · ' . $solicitud->nombreCompleto());

            if ($simulacion) {
                continue;
            }

            try {
                $anuladas += $this->anular($solicitud->scr_id, $stock) ? 1 : 0;
            } catch (\Throwable $e) {
                // Una solicitud que falla no puede frenar el barrido: las
                // demás siguen teniendo stock bloqueado sin motivo.
                $this->error("    No se pudo anular {$solicitud->scr_numero}: {$e->getMessage()}");
            }
        }

        if (! $simulacion) {
            $this->info("{$anuladas} solicitud(es) anulada(s) con su stock liberado.");
        }

        return self::SUCCESS;
    }

    /**
     * Re-lee la fila CON BLOQUEO antes de anular: si el cliente estaba
     * confirmando su pedido justo en ese instante, el que llega segundo ve
     * el estado ya cambiado (CONVERTIDA) y se retira sin tocar nada.
     */
    private function anular(int $id, StockCreditoService $stock): bool
    {
        return DB::transaction(function () use ($id, $stock) {
            $solicitud = SolicitudCredito::whereKey($id)
                ->lockForUpdate()
                ->with(['estado', 'detalles.producto'])
                ->first();

            if (! $solicitud || ! $solicitud->plazoVencido()) {
                return false;
            }

            $codigoAnterior = $solicitud->estado->esc_codigo;
            $anterior       = (int) $solicitud->scr_escid;
            $anulada        = EstadoSolicitud::porCodigo(EstadoSolicitud::ANULADA);

            $solicitud->update(['scr_escid' => $anulada->esc_id]);

            $stock->liberar($solicitud);

            SolicitudCreditoHistorial::create([
                'sch_scrid'  => $solicitud->scr_id,
                'sch_escant' => $anterior,
                'sch_escnue' => $anulada->esc_id,
                'sch_usuid'  => null,
                'sch_observ' => $codigoAnterior === EstadoSolicitud::ACEPTADA
                    ? 'Plazo vencido sin completar el pedido: solicitud anulada y stock liberado.'
                    : 'Oferta vencida sin respuesta del cliente: solicitud anulada y stock liberado.',
            ]);

            return true;
        });
    }

    private function solicitudesVencidas(int $limite)
    {
        return SolicitudCredito::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('esc_codigo', self::ESTADOS_QUE_VENCEN))
            ->whereNotNull('scr_vighas')
            ->whereDate('scr_vighas', '<', now()->toDateString())
            ->with('estado')
            ->orderBy('scr_vighas')
            ->limit($limite)
            ->get();
    }
}