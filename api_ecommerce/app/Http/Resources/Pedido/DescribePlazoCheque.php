<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\PagoCheque;

/**
 * Plazo para acercar el cheque físico a la sucursal, compartido por el
 * listado y el detalle de "Mis Pedidos".
 *
 * Es un tercer reloj del pedido, distinto de los dos que ya existen:
 *
 *   ped_vence    -> plazo para declarar el pago (Paso 3 del checkout).
 *   ped_vencret  -> plazo para retirar un pedido ya LISTO.
 *   pch_fevcto   -> plazo para entregar el papel.
 *
 * Vive aparte porque un pedido con cheque declarado ya no está pendiente
 * de pago en el sentido del checkout (no hay nada que hacer en el Paso 3,
 * ped_vence quedó en null) y tampoco está todavía listo para retirar. Si
 * se colgara de cualquiera de los otros dos, el cliente vería un contador
 * que le pide algo distinto de lo que realmente tiene que hacer.
 */
trait DescribePlazoCheque
{
    /**
     * Plazo más cercano entre los cheques todavía no recibidos. null
     * cuando el pedido no tiene cheques o cuando ya llegaron todos: una
     * vez recibido el papel el reloj deja de correr, y seguir mostrándolo
     * haría pensar que falta entregar algo.
     *
     * Con pago dividido los cheques nacen juntos, así que los plazos
     * coinciden; tomar el menor garantiza que el aviso nunca prometa más
     * tiempo del que hay.
     */
    protected function plazoCheque(): ?array
    {
        $pendientes = $this->pagos
            ->map(fn ($pago) => $pago->cheque)
            ->filter(fn ($cheque) => $cheque?->estaPendiente())
            ->filter(fn ($cheque) => $cheque->pch_fevcto !== null);

        if ($pendientes->isEmpty()) {
            return null;
        }

        $vence = $pendientes
            ->sortBy(fn (PagoCheque $cheque) => $cheque->pch_fevcto->getTimestamp())
            ->first()
            ->pch_fevcto;

        return [
            'vence'       => $vence->toIso8601String(),
            'horas_plazo' => PagoCheque::HORAS_PLAZO,
            'cantidad'    => $pendientes->count(),
        ];
    }
}