<?php

namespace App\Jobs;

use App\Models\Venta\VentaCab;
use App\Services\Facturacion\FacturacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Declara una factura de venta ante la DNIT, fuera de la peticion que
 * confirmo el cobro.
 *
 * Recibe el ID y no el modelo: cuando el job corre, lee la venta fresca.
 *
 * Reintentos (guia 8, regla 3: "con pausa entre intentos, nunca un bucle
 * inmediato"):
 *   - Solo se reintenta ante una causa TRANSITORIA (corte de red, 502,
 *     503, 429...). Se lanza una excepcion a proposito para que la cola
 *     aplique la espera creciente de backoff().
 *   - Un dato invalido o un rechazo NO se reintenta: el job termina bien y
 *     la causa queda en venta_fe.vfe_error para que una persona la corrija.
 *
 * Todos los reintentos usan el MISMO numero de factura: lo asigno
 * NumeracionVentaService una sola vez y esta en venta_cab.
 *
 * Si el worker estuviera caido o el job se perdiera, no se pierde nada: la
 * verdad esta en venta_fe (PENDIENTE) y el comando facturacion:reintentar
 * la vuelve a encolar.
 */
class DeclararFacturaElectronica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** 1 intento inicial + 3 reintentos. */
    public int $tries = 4;

    /** Mayor que el timeout HTTP (120 s) mas la firma y el KuDE. */
    public int $timeout = 150;

    public function __construct(public int $ventaId) {}

    /** Espera, en segundos, antes de cada reintento: 1, 5 y 15 minutos. */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(FacturacionService $servicio): void
    {
        $venta = VentaCab::find($this->ventaId);

        if (!$venta) {
            return;
        }

        $fe = $servicio->declarar($venta);

        if ($fe->esReintentable()) {
            throw new RuntimeException(
                "Factura electronica de la venta {$this->ventaId} pendiente ({$fe->vfe_error}): se reintentara."
            );
        }
    }

    /**
     * Se agotaron los reintentos de la cola. No es el final: la venta sigue
     * PENDIENTE en venta_fe y el barrido programado la retoma mas tarde.
     */
    public function failed(Throwable $e): void
    {
        Log::warning('Declaracion electronica sin completar tras los reintentos de la cola', [
            'venta'   => $this->ventaId,
            'mensaje' => $e->getMessage(),
        ]);
    }
}