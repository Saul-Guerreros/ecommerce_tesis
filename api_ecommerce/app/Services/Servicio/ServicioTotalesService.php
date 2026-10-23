<?php

namespace App\Services\Servicio;

use App\Models\Servicio\SolicitudServicio;

/**
 * Consolida los totales de la solicitud (sse_subtot / sse_impues / sse_total)
 * y abre el eje de cobro. Lo usan el cierre "no reparable" (Paso 3) y el
 * cierre de la OT (Paso 5): un solo lugar define cómo se arma el total.
 *
 *   sse_subtot = mano de obra + repuestos (precios con IVA incluido)
 *   sse_impues = IVA contenido en sse_subtot (informativo, como ped_impues)
 *   sse_total  = sse_subtot + sse_cosret (retiro) + sse_cosenv (envío de egreso)
 *
 * Garantía (regla 8): el cliente no paga nada, ni siquiera el retiro.
 */
class ServicioTotalesService
{
    public function __construct(private EstadoPagoServicioService $estadoPago) {}

    public function consolidar(SolicitudServicio $s, float $subtot, float $impues, ?int $usuId): void
    {
        if ($s->sse_garant) {
            $subtot = $impues = 0.0;
            $s->sse_cosret = 0;
            $s->sse_cosenv = 0;
        }

        $total = round($subtot + (float) $s->sse_cosret + (float) $s->sse_cosenv, 2);

        $s->sse_subtot = round($subtot, 2);
        $s->sse_impues = round($impues, 2);
        $s->sse_total  = $total;

        $s->save();

        // El eje de cobro se DERIVA de los pagos (EstadoPagoServicioService):
        // sin pagos queda PENDIENTE_PAGO si hay total, o NULL si el total es 0;
        // con anticipos confirmados queda PARCIAL o, si ya cubren todo, CONFIRMADO.
        $this->estadoPago->recalcular($s, $usuId,
            $total > 0 ? 'Total a cobrar: Gs. ' . number_format($total, 0, ',', '.') : null);
    }

    /** IVA contenido en un precio con IVA incluido (mismo cálculo que Producto::ivaIncluidoEn). */
    public static function ivaIncluido(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa)) : 0.0;
    }
}
