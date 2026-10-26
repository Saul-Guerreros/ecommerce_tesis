<?php

namespace App\Console\Commands;

use App\Jobs\DeclararFacturaElectronica;
use App\Models\Venta\VentaFe;
use Illuminate\Console\Command;

/**
 * Red de seguridad de la facturacion electronica.
 *
 * La cola declara cada factura apenas se cobra. Este comando cubre lo que
 * la cola pudo dejar atras: el worker estuvo apagado, un job se perdio, o
 * la API estuvo caida mas tiempo que los reintentos del job.
 *
 * Sin opciones (lo que corre el scheduler): encola las facturas PENDIENTES
 * de causa transitoria que llevan mas de 10 minutos sin intentarse. La
 * guia pide separar los intentos y no repetirlos en bucle; ademas la API
 * limita a 60 peticiones por minuto, por eso el lote es chico.
 *
 * Con --venta=ID: reencola esa venta aunque su error exija correccion.
 * Sirve cuando ya corregiste el problema (cargaste la sede, actualizaste
 * el token, completaste los datos de la empresa...).
 */
class ReintentarFacturasElectronicas extends Command
{
    /** Pendientes con mas intentos que esto necesitan una persona. */
    private const MAX_INTENTOS = 20;

    private const MINUTOS_ENTRE_INTENTOS = 10;

    protected $signature = 'facturacion:reintentar
                            {--venta= : ID de una venta concreta (reintenta aunque su error exija correccion)}
                            {--limite=10 : Maximo de facturas a encolar por pasada}';

    protected $description = 'Vuelve a encolar las facturas electronicas pendientes de declarar';

    public function handle(): int
    {
        if (!config('facturacion.activa')) {
            $this->warn('La facturacion electronica esta desactivada (FACTURACION_ACTIVA=false).');

            return self::SUCCESS;
        }

        if ($this->option('venta') !== null) {
            return $this->reintentarUna((int) $this->option('venta'));
        }

        $pendientes = VentaFe::reintentables()
            ->where('vfe_intentos', '<', self::MAX_INTENTOS)
            // Desde el ultimo intento o, si nunca hubo, desde que se creo:
            // asi no se pisa el job recien despachado de una venta nueva.
            ->whereRaw(
                'COALESCE(vfe_ultimo_intento, vfe_creado) < ?',
                [now()->subMinutes(self::MINUTOS_ENTRE_INTENTOS)]
            )
            ->orderBy('vfe_id')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();

        foreach ($pendientes as $fe) {
            DeclararFacturaElectronica::dispatch($fe->vfe_venid);
        }

        $this->info("Facturas encoladas para reintento: {$pendientes->count()}");

        return self::SUCCESS;
    }

    private function reintentarUna(int $ventaId): int
    {
        $fe = VentaFe::where('vfe_venid', $ventaId)->first();

        if (!$fe) {
            $this->error("La venta {$ventaId} no tiene registro de facturacion electronica.");

            return self::FAILURE;
        }

        if ($fe->estaEnviado() || $fe->estaRechazado()) {
            $this->info('Esa factura ya tiene resultado final (ENVIADO o RECHAZADO): no se reintenta.');

            return self::SUCCESS;
        }

        DeclararFacturaElectronica::dispatch($ventaId);
        $this->info("Venta {$ventaId} encolada.");

        return self::SUCCESS;
    }
}