<?php

namespace App\Jobs;

use App\Models\Venta\VentaFe;
use App\Services\Facturacion\FacturaCorreoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pide a la API el correo de una factura ya cobrada (POST /facturas/{cdc}/correo).
 * Si sigue pendiente se lanza una excepción para que la cola reintente con
 * pausa; agotados los reintentos, lo retoma facturacion:correos.
 */
class SolicitarCorreoFactura implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(public int $feId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(FacturaCorreoService $correo): void
    {
        $fe = VentaFe::find($this->feId);

        if (!$fe) {
            return;
        }

        $fe = $correo->solicitar($fe);

        if ($fe->vfe_correo_pend) {
            throw new RuntimeException("Correo de la factura (venta_fe {$this->feId}) pendiente: se reintentará.");
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Correo de factura sin completar tras los reintentos de la cola', [
            'venta_fe' => $this->feId,
            'mensaje'  => $e->getMessage(),
        ]);
    }
}