<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;

/**
 * El catálogo estado_pago.PENDIENTE describe genéricamente "pago sin
 * verificar", pero esa descripción ("Pendiente de verificación") es
 * engañosa cuando el pedido se pagó en efectivo contra entrega: ahí no
 * hay ningún comprobante esperando revisión, sólo falta que el cliente
 * pague cuando reciba el pedido.
 *
 * Se usa tanto en PedidoClienteListaResource como en
 * PedidoClienteDetalleResource — por eso vive en un trait y no repetido
 * en los dos archivos.
 */
trait DescribeEstadoPago
{
    private function descripcionEstadoPago(): ?string
    {
        $estado = $this->estadoPago;

        if ($estado?->epa_codigo === EstadoPago::PENDIENTE && $this->esSoloContraentrega()) {
            return 'Pendiente de pago contra entrega';
        }

        return $estado?->epa_descri;
    }

    /**
     * true cuando TODOS los pagos del pedido son en efectivo contra
     * entrega. Con pago dividido (p. ej. transferencia + efectivo) esto
     * da false, y el texto genérico del catálogo sigue siendo correcto:
     * ahí sí hay una transferencia real esperando que el admin la
     * verifique.
     *
     * Requiere 'pagos.metodoPago' cargado (PedidoClienteService::RELACIONES
     * ya lo incluye).
     */
    private function esSoloContraentrega(): bool
    {
        $pagos = $this->pagos;

        return $pagos->isNotEmpty()
            && $pagos->every(fn ($p) => $p->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA);
    }
}