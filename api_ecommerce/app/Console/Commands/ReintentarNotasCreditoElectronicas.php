<?php

namespace App\Console\Commands;

use App\Jobs\DeclararNotaCreditoElectronica;
use App\Models\Venta\NotaCreditoVentaFe;
use Illuminate\Console\Command;

/**
 * Red de seguridad de la NC electronica, equivalente a
 * facturacion:reintentar para las facturas.
 *
 * Cubre lo que la cola pudo dejar atras: el worker apagado, un job perdido
 * o la API caida mas tiempo que los reintentos del job.
 */
class ReintentarNotasCreditoElectronicas extends Command
{
    private const MAX_INTENTOS = 20;

    private const MINUTOS_ENTRE_INTENTOS = 10;

    protected $signature = 'facturacion:reintentar-nc
                            {--nota= : ID de una NC concreta (reintenta aunque su error exija correccion)}
                            {--limite=10 : Maximo de notas a encolar por pasada}';

    protected $description = 'Vuelve a encolar las notas de credito electronicas pendientes de declarar';

    public function handle(): int
    {
        if (!config('facturacion.activa')) {
            $this->warn('La facturacion electronica esta desactivada (FACTURACION_ACTIVA=false).');

            return self::SUCCESS;
        }

        if ($this->option('nota') !== null) {
            return $this->reintentarUna((int) $this->option('nota'));
        }

        $pendientes = NotaCreditoVentaFe::reintentables()
            ->where('nfe_intentos', '<', self::MAX_INTENTOS)
            ->whereRaw(
                'COALESCE(nfe_ultimo_intento, nfe_creado) < ?',
                [now()->subMinutes(self::MINUTOS_ENTRE_INTENTOS)]
            )
            ->orderBy('nfe_id')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();

        foreach ($pendientes as $fe) {
            DeclararNotaCreditoElectronica::dispatch($fe->nfe_ncvid);
        }

        $this->info("Notas de credito encoladas para reintento: {$pendientes->count()}");

        return self::SUCCESS;
    }

    private function reintentarUna(int $notaId): int
    {
        $fe = NotaCreditoVentaFe::where('nfe_ncvid', $notaId)->first();

        if (!$fe) {
            $this->error("La nota de credito {$notaId} no tiene registro de declaracion electronica.");

            return self::FAILURE;
        }

        if ($fe->estaEnviado() || $fe->estaRechazado()) {
            $this->info('Esa NC ya tiene resultado final (ENVIADA o RECHAZADA): no se reintenta.');

            return self::SUCCESS;
        }

        DeclararNotaCreditoElectronica::dispatch($notaId);
        $this->info("Nota de credito {$notaId} encolada.");

        return self::SUCCESS;
    }
}