<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Pedido\EstadoPedido;

/**
 * Pantalla completa de Gestión del Pedido.
 *
 * Las tarjetas ENTREGA y FACTURACIÓN se arman desde pedido_entrega y
 * pedido_facturacion, que son SNAPSHOTS tomados en el checkout. No se
 * leen de direccion ni de dato_fiscal: si el cliente edita su dirección
 * mañana, el pedido tiene que seguir mostrando a dónde se envió.
 *
 * Los contadores de pago (verificado / pendiente / porcentaje) se
 * calculan acá y no en Angular para que la barra de progreso y el badge
 * del encabezado no puedan contradecirse entre sí.
 */
class PedidoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pagos = $this->relationLoaded('pagos') ? $this->pagos : collect();

        // El monto realmente cobrado sale de cobro_venta. ppa_montver es el
        // respaldo para los pedidos cobrados con el flujo anterior.
        $montoVerificado = (float) $pagos
            ->filter(fn ($p) => $p->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO)
            ->sum(fn ($p) => (float) ($p->cobro?->cob_monto ?? $p->ppa_montver ?? $p->ppa_monto));

        // Cuantos comprobantes faltan validar: con uno pendiente, la factura
        // del pedido todavia no se puede emitir y el cobro queda bloqueado.
        $comprobantesPendientes = $pagos
            ->filter(fn ($p) => (bool) $p->metodoPago?->mpa_requcom)
            ->filter(fn ($p) => !$p->comprobante?->pco_verifi)
            ->count();

        $total = (float) $this->ped_total;

                // Un pago contra entrega (efectivo) obliga a esperar a LISTO: la
        // factura se imprime y se entrega junto con la mercaderia.
        $hayEfectivo = $pagos->contains(fn ($p) => !$p->metodoPago?->mpa_requcom);
        $estaListo   = $this->estadoPedido?->est_codigo === EstadoPedido::LISTO;

        $tieneFactura = $this->factura !== null;

        // Regla de habilitacion de [GENERAR FACTURA]. La pantalla la repite;
        // la que manda es VentaService::validarHabilitacion().
        $puedeGenerarFactura = !$tieneFactura
            && $comprobantesPendientes === 0
            && (!$hayEfectivo || $estaListo);

        return [
            // ---------- 1. Encabezado ----------
            'ped_id' => $this->ped_id,
            'numero' => $this->ped_numero,
            'creado' => $this->ped_creado?->format('d/m/Y H:i'),
            'actualizado' => $this->ped_actual?->format('d/m/Y H:i'),

            'estado_pago' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],
            'estado_pedido' => [
                'codigo'      => $this->estadoPedido?->est_codigo,
                'descripcion' => $this->estadoPedido?->est_descri,
            ],

            // Los dos relojes del documento de máquinas de estado (pago y
            // retiro) nunca corren a la vez: en la práctica sólo uno de
            // los dos llega con valor. Se devuelven los dos de todas
            // formas, sin resolver cuál mostrar — esa es decisión de
            // presentación del Angular, no del backend.
            'ped_vence'   => $this->ped_vence?->toIso8601String(),
            'ped_vencret' => $this->ped_vencret?->toIso8601String(),
            'retiro_vencido' => $this->retiroVencido(),

            // Combo «Cambiar estado»: lo arma PedidoEstadoService y llega
            // por withTransiciones() desde el controlador.
            'transiciones' => $this->additional['transiciones'] ?? [],

            'nota_interna' => $this->ped_obsint,

            // ---------- 2.1 Tarjeta CLIENTE ----------
            'cliente' => $this->armarCliente(),

            // ---------- 2.2 Tarjeta ENTREGA ----------
            'entrega' => $this->armarEntrega(),

            // ---------- 3.1 Tarjeta PRODUCTOS ----------
            'productos' => DetallePedidoResource::collection(
                $this->whenLoaded('detalles', fn () => $this->detalles, collect())
            ),
            'totales' => [
                'subtotal'     => (float) $this->ped_subtot,
                'iva_incluido' => (float) $this->ped_impues,
                'costo_envio'  => (float) $this->ped_cosenv,
                'total'        => $total,
            ],

            // ---------- 3.2 Tarjeta DATOS DE FACTURACIÓN ----------
            // Solo la cabecera declarada por el cliente en el Paso 2.
            // El comprobante fiscal (venta + detalle) es otra instrucción.
            'facturacion' => $this->armarFacturacion($puedeGenerarFactura),

            // ---------- 3.3 Tarjeta PAGO / PAGOS ----------
            'pago' => [
                'dividido'          => (bool) $this->ped_pagdiv,
                'cantidad'          => $pagos->count(),
                'total_pedido'      => $total,
                'monto_verificado'  => $montoVerificado,
                'monto_pendiente'   => max(0.0, $total - $montoVerificado),
                'porcentaje'        => $total > 0 ? (int) round($montoVerificado / $total * 100) : 0,
                'items'             => PedidoPagoDetalleResource::collection($pagos),
                'comprobantes_pendientes' => $comprobantesPendientes,
                // [Realizar cobro] queda deshabilitado hasta que exista la
                // factura: el cobro se aplica contra el documento fiscal.
                'rechazos'                => (int) $this->ped_rechaz,
                'rechazos_restantes'      => $this->rechazosRestantes(),
                'cobro_habilitado'  => $tieneFactura,
            ],
        ];
    }

    // ---------- Armadores de tarjeta ----------

    private function armarCliente(): array
    {
        $cliente = $this->cliente;
        $persona = $cliente?->persona;

        return [
            'cli_id' => $cliente?->cli_id,
            'nombre' => $persona
                ? trim(($persona->per_nombr1 ?? '') . ' ' . ($persona->per_apell1 ?? ''))
                : 'Sin cliente asociado',
            'cedula'   => $persona?->per_cedula,
            'correo'   => $this->contacto($persona, ['EMAIL', 'CORREO'])
                ?? $persona?->usuario?->usu_login,
            'telefono' => $this->contacto($persona, ['CELULAR', 'TELEFONO']),
            'tipo'     => $cliente?->tipoCliente?->tpc_descri ?? 'Consumidor Final',

            // «Cliente desde»: no hay columna de alta en cliente, así que se
            // usa la fecha del primer pedido registrado, que es el dato real
            // que el sistema tiene.
            'cliente_desde'     => $this->additional['cliente_desde'] ?? null,
            'pedidos_anteriores' => $this->additional['pedidos_anteriores'] ?? 0,

        ];
    }

    private function armarEntrega(): ?array
    {
        $entrega = $this->entrega;

        if (!$entrega) {
            return null;
        }

        $esRetiro = $this->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO;

        $base = [
            'modalidad'        => $esRetiro ? 'RETIRO' : 'ENVIO',
            'modalidad_label'  => $esRetiro ? 'Retiro en sucursal' : 'Envío a domicilio',

            // El checkout guarda nombre y apellido del receptor solo cuando
            // es un tercero; si es el titular copia los datos de la persona.
            'receptor' => [
                'nombre'   => trim(($entrega->pen_nombre ?? '') . ' ' . ($entrega->pen_apelli ?? '')),
                'cedula'   => $entrega->pen_nrodoc,
                'telefono' => $entrega->pen_telef,
                'es_tercero' => $this->receptorEsTercero($entrega),
            ],
        ];

        if ($esRetiro) {
            $sucursal = $entrega->sucursal;

            return $base + [
                'sucursal' => [
                    'nombre'    => $sucursal?->suc_nombre,
                    'direccion' => $sucursal?->suc_direccion,
                    'horario'   => $sucursal?->suc_horario,
                    'ciudad'    => $sucursal?->ciudad?->ciu_nombre ?? $entrega->pen_ciudad,
                ],
                'direccion' => null,
            ];
        }

        return $base + [
            'sucursal'  => null,
            'direccion' => [
                'calle'        => $entrega->pen_calle,
                'nro_casa'     => $entrega->pen_nrocasa,
                'barrio'       => $entrega->pen_barrio,
                'ciudad'       => $entrega->pen_ciudad,
                'departamento' => $entrega->pen_departa,
                'referencia'   => $entrega->pen_referencia,
            ],
        ];
    }

    /**
     * Es un tercero si la cédula del receptor no coincide con la del
     * titular del pedido. Se compara solo por dígitos porque el checkout
     * acepta la cédula con o sin separadores.
     */
    private function receptorEsTercero($entrega): bool
    {
        $titular  = preg_replace('/\D/', '', (string) $this->cliente?->persona?->per_cedula);
        $receptor = preg_replace('/\D/', '', (string) $entrega->pen_nrodoc);

        return $titular !== '' && $receptor !== '' && $titular !== $receptor;
    }

     private function armarFacturacion(bool $puedeGenerar): ?array
    {
        $facturacion = $this->facturacion;

        if (!$facturacion) {
            return null;
        }

        $esRuc = $facturacion->pfa_tipodoc === 'RUC';

        return [
            'tipo_documento' => $facturacion->pfa_tipodoc,
            'tipo_label'     => $esRuc ? 'RUC' : 'CI (Consumidor Final)',
            'nro_documento'  => $facturacion->pfa_nrodoc,
            'razon_social'   => $facturacion->pfa_raznom,
            'telefono'       => $facturacion->pfa_telefono,
            'correo'         => $facturacion->pfa_correo,
            'recibe_por'     => $facturacion->pfa_recibe,

            // A dónde se enviará el comprobante según el canal elegido.
            'destino' => $facturacion->pfa_recibe === 'WHATSAPP'
                ? $facturacion->pfa_telefono
                : $facturacion->pfa_correo,

            // Emitida de verdad = ya tiene número fiscal (estados 1 o 2).
            'comprobante_emitido' => $this->factura !== null
                && in_array((int) $this->factura->ven_estado, [1, 2], true),

            // Emitida de verdad = ya tiene número fiscal (estados 1 o 2).
                        'comprobante_emitido' => $this->factura !== null
                && in_array((int) $this->factura->ven_estado, [1, 2], true),

            // Habilitacion del boton [GENERAR FACTURA].
            'puede_generar' => $puedeGenerar,

            // Factura del pedido: la genera el admin con [GENERAR FACTURA].
            'factura' => $this->factura ? [
                'ven_id'         => $this->factura->ven_id,
                'estado'         => (int) $this->factura->ven_estado,  // 1 Emitida, 2 Cobrada, 9 Anulada
                'numero'         => $this->factura->ven_nrodoc,
                'fe_estado'      => $this->factura->fe?->vfe_estado,
                'puede_reenviar' => $this->factura->fe?->estaEnviado() === true
                                    && !empty($this->factura->fe->vfe_xml_ruta),
                'correo_enviado' => $this->factura->fe?->vfe_correo_enviado,
                'correo_detalle' => $this->factura->fe?->vfe_correo_detalle,
                'empresa'        => $this->factura->ven_emp_razon,
                'tiene_pdf'      => !empty($this->factura->fe?->vfe_pdf_ruta),
            ] : null,
        ];
    }

    private function contacto($persona, array $tipos): ?string
    {
        if (!$persona || !$persona->relationLoaded('contactos')) {
            return null;
        }

        return $persona->contactos
            ->whereIn('con_tipo', $tipos)
            ->sortBy(fn ($c) => array_search($c->con_tipo, $tipos, true))
            ->first()?->con_valor;
    }
}