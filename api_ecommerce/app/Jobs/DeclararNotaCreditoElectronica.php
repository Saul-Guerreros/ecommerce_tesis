<?php

namespace App\Jobs;

use App\Models\Venta\NotaCreditoVenta;
use App\Services\Facturacion\NotaCreditoFeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Declara una Nota de Credito de venta ante la DNIT, fuera de la peticion
 * que la emitio. Espejo de DeclararFacturaElectronica.
 *
 * Recibe el ID y no el modelo: cuando el job corre, lee la NC fresca.
 * Todos los reintentos usan el MISMO numero, que ya esta grabado.
 */
class DeclararNotaCreditoElectronica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 4;
    public int $timeout = 150;

    public function __construct(public int $notaId) {}

    /** 1, 5 y 15 minutos (guia 8, regla 3: nunca un bucle inmediato). */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotaCreditoFeService $servicio): void
    {
        $nota = NotaCreditoVenta::find($this->notaId);

        if (!$nota) {
            return;
        }

        $fe = $servicio->declarar($nota);

        if ($fe->esReintentable()) {
            throw new RuntimeException(
                "Nota de credito electronica {$this->notaId} pendiente ({$fe->nfe_error}): se reintentara."
            );
        }
    }

    /** No es el final: la NC sigue PENDIENTE y el barrido programado la retoma. */
    public function failed(Throwable $e): void
    {
        Log::warning('Declaracion electronica de NC sin completar tras los reintentos de la cola', [
            'nota'    => $this->notaId,
            'mensaje' => $e->getMessage(),
        ]);
    }
}