<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de "Mis Pedidos" (lado cliente).
 *
 * Contraparte de PedidoListaResource (Panel Admin), pero deliberadamente
 * más chica: sin datos de "cliente" (es el que está mirando), sin
 * desglose de verificación por pago (eso es trabajo del admin, no algo
 * que el cliente necesite auditar fila por fila). Sólo lo que el cliente
 * necesita para identificar el pedido y saber si tiene algo que hacer.
 */
class PedidoClienteListaResource extends JsonResource
{
    use DescribeEstadoPago;

    public function toArray(Request $request): array
    {
        return [
            'ped_numero' => $this->ped_numero,
            'ped_total'  => (float) $this->ped_total,
            'ped_creado' => $this->ped_creado?->toIso8601String(),

            'entrega' => [
                'codigo'   => $this->tipoEntrega?->ten_codigo,
                'etiqueta' => $this->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO
                    ? 'Retiro en Sucursal'
                    : 'Envío a domicilio',
            ],

            'estado_pedido' => [
                'codigo'      => $this->estadoPedido?->est_codigo,
                'descripcion' => $this->estadoPedido?->est_descri,
            ],
            'estado_pago' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->descripcionEstadoPago(),
            ],

            // true -> el listado muestra el botón "Pagar ahora" en esta
            // fila. Lo decide el backend con la misma regla que usa el
            // guard del Paso 3 (pagable()), para que nunca queden
            // desincronizados.
            'pagable'   => $this->pagable(),
            // Mismo criterio que PedidoClienteDetalleResource: el plazo se
            // muestra en los dos casos en que el cliente tiene algo
            // pendiente de su lado (sin esto, una fila RECHAZADO no
            // mostraba su cuenta regresiva en el listado).
            'ped_vence' => in_array($this->estadoPago?->epa_codigo, Pedido::ESTADOS_PAGO_EXPIRABLES, true)
                ? $this->ped_vence?->toIso8601String()
                : null,

            // Los dos relojes nunca corren a la vez, así que el listado
            // puede mostrar el que tenga valor sin ambigüedad.
            'ped_vencret' => $this->ped_vencret?->toIso8601String(),
        ];
    }
}