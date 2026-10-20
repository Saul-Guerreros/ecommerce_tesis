<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\Pedido;

/**
 * Estado agregado del pago de un pedido (pedido.ped_epaid), derivado de
 * los pagos individuales de pedido_pago.
 *
 * Vive en su propio service porque hay DOS caminos que cambian el estado
 * de un pago y los dos tienen que llegar al mismo resultado:
 *
 *   - El admin confirma o rechaza un pago (PagoVerificacionService).
 *   - El cliente reemplaza el comprobante de un pago rechazado
 *     (PagoPedidoService::reemplazarComprobante).
 *
 * Si cada uno calculara el agregado por su cuenta, alcanzaría con que uno
 * de los dos olvidara un caso para que la bandeja del admin y "Mis
 * Pedidos" muestren cosas distintas del mismo pedido.
 */
class EstadoPagoPedidoService
{
    /**
     * Recalcula pedido.ped_epaid a partir de los pagos individuales.
     *
     * Esta columna es la que lee la bandeja para filtrar y ordenar, así
     * que tiene que mantenerse sincronizada acá: calcularla en cada
     * lectura impediría paginar y filtrar en servidor.
     *
     * Orden intencional del match: un rechazo exige acción del cliente
     * (subir un comprobante nuevo), así que no puede quedar tapado por
     * PARCIAL sólo porque además haya otro pago ya confirmado.
     *
     * @return bool true si el pedido ACABA de quedar totalmente pagado
     */
    public function recalcular(Pedido $pedido): bool
    {
        $anterior = $pedido->estadoPago?->epa_codigo;

        $pagos = $pedido->pagos()->with('estadoPago')->get();

        $total       = $pagos->count();
        $confirmados = $pagos->where('estadoPago.epa_codigo', EstadoPago::CONFIRMADO)->count();
        $rechazados  = $pagos->where('estadoPago.epa_codigo', EstadoPago::RECHAZADO)->count();

        $codigo = match (true) {
            $total > 0 && $confirmados === $total => EstadoPago::CONFIRMADO,
            $rechazados > 0                       => EstadoPago::RECHAZADO,
            $confirmados > 0                      => EstadoPago::PARCIAL,
            default                               => EstadoPago::PAGO_VERIFICAR,
        };

        $pedido->update(['ped_epaid' => EstadoPago::porCodigo($codigo)->epa_id]);
        $pedido->load('estadoPago');

        return $codigo === EstadoPago::CONFIRMADO && $anterior !== EstadoPago::CONFIRMADO;
    }

    /**
     * El rechazo devuelve la pelota al cliente: tiene que subir otro
     * comprobante. Para eso el pedido vuelve a tener plazo, contado desde
     * el rechazo y no desde la confirmación original — si se reutilizara
     * el ped_vence viejo (ya NULL desde que declaró su pago, o vencido),
     * la reserva quedaría sin límite y el barrido nunca lo alcanzaría.
     *
     * Sólo se reabre si el pedido quedó efectivamente en RECHAZADO.
     */
    public function reabrirPlazo(Pedido $pedido): void
    {
        if ($pedido->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO) {
            return;
        }

        $pedido->update([
            'ped_vence' => now()->addHours(PedidoService::HORAS_PARA_PAGAR),
        ]);
    }

    /**
     * El cliente ya corrigió: el pedido vuelve a la bandeja de
     * verificación y sale del barrido de expiración, igual que cuando
     * declaró su pago por primera vez.
     */
    public function cerrarPlazo(Pedido $pedido): void
    {
        $pedido->update(['ped_vence' => null]);
    }
}