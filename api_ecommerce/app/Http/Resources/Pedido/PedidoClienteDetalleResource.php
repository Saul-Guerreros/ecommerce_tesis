<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de un pedido en "Mis Pedidos" (lado cliente).
 *
 * Contraparte de PedidoDetalleResource (Panel Admin). No lo reutiliza:
 * ese resource trae 'nota_interna' (la observación que el admin se deja
 * a sí mismo) y 'transiciones' (el combo de cambio de estado), que son
 * herramientas de gestión y no deben llegar nunca a la tienda.
 *
 * La tarjeta ENTREGA y FACTURACIÓN se arman desde pedido_entrega y
 * pedido_facturacion, snapshots tomados en el checkout — igual que en el
 * resource admin, y por la misma razón: si el cliente edita su dirección
 * mañana, el pedido tiene que seguir mostrando a dónde se mandó éste.
 */
class PedidoClienteDetalleResource extends JsonResource
{
    use DescribeEstadoPago;

    public function toArray(Request $request): array
    {
        return [
            'ped_numero' => $this->ped_numero,
            'ped_creado' => $this->ped_creado?->toIso8601String(),
            // El plazo se muestra en los dos casos en que la pelota está
            // del lado del cliente: nunca declaró su pago, o tiene un
            // comprobante rechazado que puede reemplazar. En el resto el
            // pedido no espera nada de él y un contador sólo confundiría.
            'ped_vence'  => in_array(
                $this->estadoPago?->epa_codigo,
                Pedido::ESTADOS_PAGO_EXPIRABLES,
                true,
            ) ? $this->ped_vence?->toIso8601String() : null,
            'pagable'    => $this->pagable(),

            // Fecha límite para pasar a buscar el pedido. Sólo existe
            // mientras está LISTO y es retiro en sucursal.
            'ped_vencret' => $this->ped_vencret?->toIso8601String(),

            'estado_pedido' => [
                'codigo'      => $this->estadoPedido?->est_codigo,
                'descripcion' => $this->estadoPedido?->est_descri,
            ],
            'estado_pago' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->descripcionEstadoPago(),
            ],

            'entrega' => $this->armarEntrega(),

            // Misma forma de salida que PagoPedidoController::entregaResumen()
            // / ::facturacionResumen(): el frontend usa un único tipo para
            // "Datos de Entrega" y "Datos de Facturación" tanto acá como en
            // el Paso 3, sin adaptar nada entre una pantalla y otra.
            'productos' => ProductoResumenResource::collection(
                $this->whenLoaded('detalles', fn () => $this->detalles, collect())
            ),

            'totales' => [
                'subtotal'    => (float) $this->ped_subtot,
                'impuestos'   => (float) $this->ped_impues,
                'costo_envio' => (float) $this->ped_cosenv,
                'total'       => (float) $this->ped_total,
            ],

            'facturacion' => $this->armarFacturacion(),

            'pagos' => $this->pagos->values()->map(fn ($p) => [
                'ppa_id'            => (int) $p->ppa_id,
                'orden'             => (int) $p->ppa_orden,
                'metodo'            => $p->metodoPago?->mpa_descri,
                'monto'             => (float) $p->ppa_monto,
                'estado'            => $p->estadoPago?->epa_codigo,
                'estado_descri'     => $p->estadoPago?->epa_descri,
                'tiene_comprobante' => $p->comprobante !== null,
                // El cliente ve su propio comprobante: ruta de tienda, no la
                // de /api/admin, que exige permiso de staff.
                'comprobante_url'   => $p->comprobante
                    ? url("/api/tienda/pedidos/{$this->ped_numero}/pagos/{$p->ppa_id}/comprobante")
                    : null,

                // Por qué se lo rechazaron. Sin esto el cliente vería
                // "Pago rechazado" sin saber qué corregir, y volvería a
                // subir el mismo archivo.
                'motivo_rechazo' => $this->armarMotivoRechazo($p),

                // Lo decide el backend, igual que 'pagable': el frontend
                // no vuelve a razonar sobre estados ni plazos.
                'reemplazable' => $this->esReemplazable($p),
            ]),
        ];
    }

    /**
     * El motivo del catálogo más el detalle que escribió el admin. Se
     * devuelven separados: el primero es la causa tipificada, el segundo
     * la explicación concreta de ESTE comprobante.
     */
    private function armarMotivoRechazo($pago): ?array
    {
        if ($pago->estadoPago?->epa_codigo !== EstadoPago::RECHAZADO) {
            return null;
        }

        return [
            'motivo'  => $pago->motivoRechazo?->mrp_descri,
            'detalle' => $pago->ppa_observ,
            'fecha'   => $pago->ppa_fechver?->toIso8601String(),
        ];
    }

    /**
     * El cliente puede subir un comprobante nuevo para este pago.
     *
     * Espeja las condiciones de PagoPedidoService::pagoRechazable(): si
     * acá dijera true y el service lo rechazara, el cliente vería un
     * formulario que siempre falla.
     */
    private function esReemplazable($pago): bool
    {
        return $pago->estadoPago?->epa_codigo === EstadoPago::RECHAZADO
            && (bool) $pago->metodoPago?->mpa_requcom
            && $this->estadoPedido?->est_codigo !== EstadoPedido::CANCELADO
            && !$this->vencido();
    }

    private function armarEntrega(): ?array
    {
        $entrega = $this->entrega;

        if (!$entrega) {
            return null;
        }

        $esRetiro = $this->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO;
        $sucursal = $entrega->sucursal;

        return [
            'modalidad'          => $esRetiro ? 'retiro' : 'envio',
            'receptor_nombre'    => trim(($entrega->pen_nombre ?? '') . ' ' . ($entrega->pen_apelli ?? '')),
            'receptor_telefono'  => $entrega->pen_telef,
            'receptor_documento' => $entrega->pen_nrodoc,

            'suc_nombre'    => $sucursal?->suc_nombre,
            'suc_direccion' => $sucursal?->suc_direccion,
            'suc_horario'   => $sucursal?->suc_horario,

            'calle'        => $entrega->pen_calle,
            'nrocasa'      => $entrega->pen_nrocasa,
            'barrio'       => $entrega->pen_barrio,
            'ciudad'       => $entrega->pen_ciudad,
            'departamento' => $entrega->pen_departa,
            'referencia'   => $entrega->pen_referencia,
        ];
    }

    private function armarFacturacion(): ?array
    {
        $facturacion = $this->facturacion;

        if (!$facturacion) {
            return null;
        }

        return [
            'tipo_documento' => $facturacion->pfa_tipodoc,
            'nro_documento'  => $facturacion->pfa_nrodoc,
            'razon_social'   => $facturacion->pfa_raznom,
            'recibe_por'     => $facturacion->pfa_recibe,
        ];
    }
}