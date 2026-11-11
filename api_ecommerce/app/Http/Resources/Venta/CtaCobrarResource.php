<?php

namespace App\Http\Resources\Venta;

use App\Models\Pedido\EstadoPago;
use App\Models\Venta\CtaCobrar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una cuota como la ve el cliente en "Mis Cuotas".
 *
 * No expone cco_id de otras facturas ni datos internos: solo lo que el
 * cliente necesita para saber cuanto debe y que hacer.
 */
class MiCuotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pago = $this->pagoVigente;
        $estado = $pago?->estadoPago?->epa_codigo;

        return [
            'cco_id'     => (int) $this->cco_id,
            'numero'     => (int) $this->cco_nrocuo,
            'vencimiento' => $this->cco_vencim?->format('Y-m-d'),
            'importe'    => round((float) $this->cco_monto, 2),
            'composicion' => $this->cco_compos,
            'estado'     => $this->cco_estado,
            'vencida'    => $this->estaVencida(),
            'fecha_cobro' => $this->cco_feccob?->format('Y-m-d'),

            // Estado de lo que el cliente declaro, si declaro algo.
            'pago' => $pago ? [
                'cpa_id'        => (int) $pago->cpa_id,
                'metodo'        => $pago->metodoPago?->mpa_descri,
                'estado'        => $estado,
                'estado_descri' => $pago->estadoPago?->epa_descri,
                'fecha_declarada' => $pago->cpa_fechpag?->format('Y-m-d'),
                'tiene_comprobante' => $pago->comprobante !== null,
                'motivo_rechazo' => $estado === EstadoPago::RECHAZADO ? [
                    'motivo'  => $pago->motivoRechazo?->mrp_descri,
                    'detalle' => $pago->cpa_observ,
                ] : null,
            ] : null,

            // Las dos acciones posibles. Las decide el backend: la pantalla
            // las obedece, no las calcula.
            'puede_pagar' => $this->cco_estado === CtaCobrar::ESTADO_PENDIENTE
                && ($pago === null || $estado === EstadoPago::RECHAZADO),

            'puede_reemplazar' => $estado === EstadoPago::RECHAZADO,
        ];
    }
}