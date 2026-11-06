<?php

namespace App\Http\Resources\Servicio;

use App\Models\Pedido\EstadoPago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago del servicio visto por el CLIENTE (C4). Misma forma que los pagos
 * de PedidoClienteDetalleResource, así pedido-detalle y el detalle del
 * servicio comparten la tarjeta de pago. No expone quién verificó/cobró.
 *
 * Requiere: metodoPago, estadoPago, comprobante, motivoRechazo y la
 * solicitud (para armar la URL de tienda).
 */
class ServicioPagoClienteResource extends JsonResource
{
    public function __construct($resource, private string $numero)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rechazado = $this->estadoPago?->epa_codigo === EstadoPago::RECHAZADO;

        return [
            'spa_id'            => (int) $this->spa_id,
            'orden'             => (int) $this->spa_orden,
            'metodo'            => $this->metodoPago?->mpa_descri,
            'monto'             => (float) $this->spa_monto,
            'estado'            => $this->estadoPago?->epa_codigo,
            'estado_descri'     => $this->estadoPago?->epa_descri,
            'tiene_comprobante' => $this->comprobante !== null,
            'comprobante_url'   => $this->comprobante
                ? url("/api/tienda/servicios/{$this->numero}/pagos/{$this->spa_id}/comprobante")
                : null,
            'motivo_rechazo'    => $rechazado ? [
                'motivo'  => $this->motivoRechazo?->mrp_descri,
                'detalle' => $this->spa_observ,
            ] : null,
            // Lo decide el backend: solo un rechazado con comprobante se corrige.
            'reemplazable'      => $rechazado && (bool) $this->metodoPago?->mpa_requcom,
        ];
    }
}
