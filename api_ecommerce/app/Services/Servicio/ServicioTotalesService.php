<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\ServicioHistorialEstado;
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

        $anterior = $s->sse_epaid;

        // Sin monto a cobrar no se abre el eje de pago (queda NULL).
        $s->sse_epaid = $total > 0 ? EstadoPago::porCodigo(EstadoPago::PENDIENTE)->epa_id : null;
        $s->save();

        if ($s->sse_epaid && $s->sse_epaid !== $anterior) {
            ServicioHistorialEstado::create([
                'she_sseid'  => $s->sse_id,
                'she_usuid'  => $usuId,
                'she_tipo'   => ServicioHistorialEstado::PAGO,
                'she_estant' => $anterior,
                'she_estid'  => $s->sse_epaid,
                'she_coment' => 'Total a cobrar: Gs. ' . number_format($total, 0, ',', '.'),
                'she_ip'     => request()->ip(),
            ]);
        }
    }

    /** IVA contenido en un precio con IVA incluido (mismo cálculo que Producto::ivaIncluidoEn). */
    public static function ivaIncluido(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa)) : 0.0;
    }
}
