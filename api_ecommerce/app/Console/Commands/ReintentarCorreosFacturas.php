<?php

namespace App\Console\Commands;

use App\Jobs\SolicitarCorreoFactura;
use App\Models\Venta\VentaFe;
use Illuminate\Console\Command;

/**
 * Red de seguridad del correo posterior al cobro: vuelve a encolar las
 * solicitudes pendientes (vfe_correo_pend) con pausa entre intentos.
 * Pasado MAX_INTENTOS, el caso queda visible para revisión manual.
 */
class ReintentarCorreosFacturas extends Command
{
    private const MAX_INTENTOS = 10;

    private const MINUTOS_ENTRE_INTENTOS = 10;

    protected $signature = 'facturacion:correos {--limite=10 : Máximo de correos a encolar por pasada}';

    protected $description = 'Vuelve a pedir el correo de las facturas cobradas pendientes de envío';

    public function handle(): int
    {
        if (!config('facturacion.activa')) {
            return self::SUCCESS;
        }

        $pendientes = VentaFe::where('vfe_correo_pend', true)
            ->where('vfe_correo_intentos', '<', self::MAX_INTENTOS)
            ->where(function ($q) {
                $q->whereNull('vfe_correo_fecha')
                  ->orWhere('vfe_correo_fecha', '<', now()->subMinutes(self::MINUTOS_ENTRE_INTENTOS));
            })
            ->orderBy('vfe_id')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();

        foreach ($pendientes as $fe) {
            SolicitarCorreoFactura::dispatch($fe->vfe_id);
        }

        $this->info("Correos encolados para reintento: {$pendientes->count()}");

        return self::SUCCESS;
    }
}