<?php

namespace App\Http\Resources\Venta;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Venta\CtaCobrar;
use App\Models\Venta\CuotaPagoMedio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una cuota como la ve el cliente en "Mis Cuotas".
 *
 * El pago se lee de pagoUltimo (no de pagoVigente): una declaracion
 * rechazada tiene que seguir visible, porque es justamente la que el
 * cliente tiene que corregir.
 */
class MiCuotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pago   = $this->relationLoaded('pagoUltimo') ? $this->pagoUltimo : null;
        $estado = $pago?->estadoPago?->epa_codigo;

        return [
            'cco_id'      => (int) $this->cco_id,
            'numero'      => (int) $this->cco_nrocuo,
            'vencimiento' => $this->cco_vencim?->format('Y-m-d'),
            'importe'     => round((float) $this->cco_monto, 2),
            'composicion' => $this->cco_compos,
            'estado'      => $this->cco_estado,
            'vencida'     => $this->estaVencida(),
            'fecha_cobro' => $this->cco_feccob?->format('Y-m-d'),

            // Desglose por producto de ESTA cuota. Lo inyecta el controlador
            // con ComposicionCuotaService.
            'productos' => $this->productos_cuota ?? [],

            'pago' => $pago ? [
                'cpa_id'        => (int) $pago->cpa_id,
                'estado'        => $estado,
                'estado_descri' => $pago->estadoPago?->epa_descri,
                'monto_total'   => round((float) $pago->cpa_monto, 2),
                'fecha'         => $pago->cpa_creado?->format('Y-m-d'),

                // Un medio por método: con pago dividido hay dos o más, y
                // cada uno tiene su propio comprobante y su propio estado.
                'medios' => $pago->medios->map(fn (CuotaPagoMedio $m) => [
                    'cpm_id'            => (int) $m->cpm_id,
                    'orden'             => (int) $m->cpm_orden,
                    'metodo'            => $m->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA
                        ? 'Efectivo, pago en sucursal'
                        : $m->metodoPago?->mpa_descri,
                    'monto'             => round((float) $m->cpm_monto, 2),
                    'estado'            => $m->estadoPago?->epa_codigo,
                    'estado_descri'     => $m->estadoPago?->epa_descri,
                    'tiene_comprobante' => $m->comprobante !== null,
                    'puede_reemplazar'  => $m->esReemplazable(),
                    'motivo_rechazo'    => $m->fueRechazado() ? [
                        'motivo'  => $m->motivoRechazo?->mrp_descri,
                        'detalle' => $m->cpm_observ,
                    ] : null,
                ])->values(),
            ] : null,

            // Las decide el backend: la pantalla las obedece, no las calcula.
            'puede_pagar' => $this->cco_estado === CtaCobrar::ESTADO_PENDIENTE
                && ($pago === null || $estado === EstadoPago::RECHAZADO),
        ];
    }
}