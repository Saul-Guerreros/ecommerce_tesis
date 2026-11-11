<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\PagoCheque;

/**
 * Eje "Cheque" de la cabecera del pedido, junto a los de Pedido y Pago.
 *
 * Devuelve null si el pedido no tiene cheques: la cabecera no muestra el
 * badge en ese caso.
 *
 * Con pago dividido hay dos cheques que pueden estar en estados distintos,
 * así que el badge los resume con esta prioridad:
 *
 *   1. Un rechazo o una falta de entrega mandan: es lo que necesita
 *      atención, aunque el otro cheque esté impecable.
 *   2. Todos acreditados / todos recibidos -> ese estado.
 *   3. Alguno recibido -> "1 de 2 recibidos".
 *   4. Ninguno -> el estado del primer cheque pendiente.
 */
trait DescribeEstadoCheque
{
    protected function estadoCheque(): ?array
    {
        $cheques = $this->pagos->map(fn ($pago) => $pago->cheque)->filter();

        if ($cheques->isEmpty()) {
            return null;
        }

        $total     = $cheques->count();
        $recibidos = $cheques->filter(fn (PagoCheque $c) => $c->fueRecibido())->count();

        // 1. Lo que necesita atención manda sobre el resto.
        foreach ([PagoCheque::RECHAZADO, PagoCheque::NO_ENTREGADO] as $critico) {
            if ($cheques->contains(fn (PagoCheque $c) => $c->pch_estado === $critico)) {
                return $this->armarEstadoCheque($critico, PagoCheque::ESTADOS[$critico], $total, $recibidos);
            }
        }

        // 2. Todos en el mismo tramo final.
        if ($cheques->every(fn (PagoCheque $c) => $c->fueAcreditado())) {
            return $this->armarEstadoCheque(
                PagoCheque::ACREDITADO,
                PagoCheque::ESTADOS[PagoCheque::ACREDITADO],
                $total,
                $recibidos,
            );
        }

        if ($recibidos === $total) {
            return $this->armarEstadoCheque(
                PagoCheque::RECIBIDO,
                PagoCheque::ESTADOS[PagoCheque::RECIBIDO],
                $total,
                $recibidos,
            );
        }

        // 3. Pago dividido a medio camino.
        if ($recibidos > 0) {
            return $this->armarEstadoCheque(
                PagoCheque::RECIBIDO,
                "{$recibidos} de {$total} recibidos",
                $total,
                $recibidos,
            );
        }

        // 4. Ninguno llegó todavía: EN_ESPERA o DESCARTADO.
        $pendiente = $cheques->first();

        return $this->armarEstadoCheque(
            $pendiente->pch_estado,
            $pendiente->descripcionEstado(),
            $total,
            $recibidos,
        );
    }

    private function armarEstadoCheque(string $codigo, string $descripcion, int $total, int $recibidos): array
    {
        return [
            'codigo'      => $codigo,
            'descripcion' => $descripcion,
            'total'       => $total,
            'recibidos'   => $recibidos,
        ];
    }
}