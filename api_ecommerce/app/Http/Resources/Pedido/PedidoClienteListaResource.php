<?php

namespace App\Http\Resources\Pedido;

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
            'ped_vence' => $this->pendienteDePago() ? $this->ped_vence?->toIso8601String() : null,
        ];
    }
}