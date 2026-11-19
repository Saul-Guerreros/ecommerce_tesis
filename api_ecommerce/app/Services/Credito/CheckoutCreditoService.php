<?php

namespace App\Services\Credito;

use App\Http\Resources\Cliente\DatoFiscalClienteResource;
use App\Http\Resources\Credito\SolicitudCuotaResource;
use App\Http\Resources\Credito\SolicitudDetalleResource;
use App\Models\Configuracion\Sucursal;
use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoHistorial;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoEntrega;
use App\Models\Pedido\PedidoFacturacion;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\TipoEntrega;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Checkout de una solicitud de crédito ACEPTADA (etapa D).
 *
 * Es la contraparte de PedidoService::confirmar(), pero la fuente de los
 * datos es la SOLICITUD y no el carrito: productos, planes, totales y
 * cronograma están congelados y el cliente ya los aceptó.
 *
 * Diferencias con el pedido al contado:
 *   - Entrega: solo retiro en casa matriz y el receptor es el titular de la
 *     solicitud (no se elige). Es la sucursal donde StockCreditoService ya
 *     reservó, así que el depósito del pedido coincide con el de la reserva.
 *   - Stock: NO se reserva de nuevo. La reserva se TRASPASA: la solicitud
 *     apaga su marca (scr_fecres) y el pedido pasa a ser su dueño, con
 *     StockPedidoService liberándola o descontándola desde ahí.
 *   - Pago: el pedido nace sin filas de pago y con estado A_CREDITO, sin
 *     ped_vence: no entra al vencimiento de 24 h ni al botón "Pagar".
 */
class CheckoutCreditoService
{
    private const CON_DETALLE = [
        'estado',
        'detalles.producto.publicacionWeb.imagenes',
        'cuotas',
    ];

    // ---------- Contexto de las pantallas ----------

    public function contexto(Usuario $usuario, int $id): array
    {
        $solicitud = $this->solicitudDelCliente($usuario, $id)->load(self::CON_DETALLE);
        $this->exigirListaParaCheckout($solicitud);

        $persona  = $usuario->persona;
        $sucursal = $this->casaMatriz();
        $nombre   = trim("{$solicitud->scr_nombre} {$solicitud->scr_apelli}");

        return [
            'solicitud' => [
                'scr_id'         => $solicitud->scr_id,
                'numero'         => $solicitud->scr_numero,
                'vigencia_texto' => $solicitud->scr_vighas?->format('d/m/Y'),
                'dias_restantes' => $solicitud->diasRestantesOferta(),
            ],

            'titular' => [
                'numero'   => $persona->per_cedula ?: $solicitud->scr_cedula,
                'nombre'   => $persona->per_nombr1 ?: $solicitud->scr_nombre,
                'apellido' => $persona->per_apell1 ?: $solicitud->scr_apelli,
                'telefono' => $solicitud->scr_celula,
                'correo'   => $usuario->usu_login,
            ],

            'datos_fiscales' => DatoFiscalClienteResource::collection(
                $persona->datosFiscales()->orderByDesc('dfi_principal')->orderByDesc('dfi_id')->get()
            )->resolve(),

            // Misma forma que espera <app-datos-entrega-card>.
            'entrega' => [
                'modalidad'          => 'retiro',
                'receptor_nombre'    => $nombre,
                'receptor_telefono'  => $solicitud->scr_celula,
                'receptor_documento' => $solicitud->scr_cedula,
                'suc_nombre'         => $sucursal?->suc_nombre,
                'suc_direccion'      => $sucursal?->suc_direccion,
                'suc_horario'        => $sucursal?->suc_horario,
                'ciudad'             => $sucursal?->ciudad?->ciu_descri,
            ],

            // Misma forma que espera <app-productos-carrito-card>.
            'items' => $solicitud->detalles->map(function ($detalle) {
                $datos = (new SolicitudDetalleResource($detalle))->resolve();

                return [
                    'imagen'   => data_get($datos, 'producto.imagen'),
                    'nombre'   => data_get($datos, 'producto.pub_titulo') ?? $detalle->scd_descri,
                    'cantidad' => (float) $detalle->scd_cantid,
                    'total'    => (float) $detalle->scd_subtot,
                ];
            })->values()->all(),

            'resumen' => [
                'total_contado' => (float) $solicitud->scr_toncon,
                'recargo'       => (float) $solicitud->scr_recfin,
                'impuestos'     => (float) $solicitud->scr_impues,
                'total'         => (float) $solicitud->scr_total,
                'plazo'         => (int) $solicitud->scr_plazo,
                'cuota_mensual' => (float) $solicitud->scr_cuomen,
            ],

            'cronograma' => SolicitudCuotaResource::collection($solicitud->cuotas)->resolve(),
        ];
    }

    // ---------- Confirmar pedido ----------

    public function confirmar(Usuario $usuario, int $id, array $datos): Pedido
    {
        return DB::transaction(function () use ($usuario, $id, $datos) {
            // Bloqueo de fila: doble clic, dos pestañas o el barrido de
            // vencimiento quedan en fila; el segundo ve el estado ya cambiado.
            $solicitud = $this->solicitudDelCliente($usuario, $id, true)->load(self::CON_DETALLE);
            $this->exigirListaParaCheckout($solicitud);

            $controlaStock = $solicitud->detalles->contains(fn ($d) => (bool) $d->producto?->prd_afestk);
            abort_if(
                $controlaStock && ! $solicitud->tieneReserva(),
                409,
                'La reserva de stock de esta solicitud ya no está vigente.'
            );

            $sucursal = $this->casaMatriz();
            abort_if(! $sucursal, 422, 'No hay una casa matriz configurada para el retiro.');

            $facturacion = $this->resolverFacturacion($usuario, $solicitud, $datos);
            $tipoEntrega = TipoEntrega::porCodigo(TipoEntrega::RETIRO);

            $pedido = Pedido::create([
                'ped_cliid'  => $solicitud->scr_cliid,
                'ped_scrid'  => $solicitud->scr_id,

                // Totales congelados de la solicitud (ya incluyen el recargo).
                'ped_subtot' => $solicitud->scr_total,
                'ped_impues' => $solicitud->scr_impues,
                'ped_cosenv' => 0,
                'ped_total'  => $solicitud->scr_total,

                'ped_tenid'  => $tipoEntrega->ten_id,
                'ped_sucid'  => $sucursal->suc_id,

                'ped_mpaid'  => null,
                'ped_pagdiv' => false,

                'ped_estid'  => EstadoPedido::porCodigo(EstadoPedido::PENDIENTE_PEDIDO)->est_id,
                'ped_epaid'  => EstadoPago::porCodigo(EstadoPago::A_CREDITO)->epa_id,
                'ped_vence'  => null,
            ]);

            $pedido->update([
                'ped_numero' => 'PED-' . date('Y') . '-' . str_pad((string) $pedido->ped_id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->crearDetalle($pedido, $solicitud);
            $this->crearEntrega($pedido, $solicitud, $sucursal, $tipoEntrega);
            $this->crearFacturacion($pedido, $facturacion);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $pedido->ped_id,
                'peh_usuid'  => $usuario->usu_id,
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => null,
                'peh_estid'  => $pedido->ped_estid,
                'peh_coment' => "Pedido a crédito creado desde la solicitud {$solicitud->scr_numero} — stock reservado.",
            ]);

            // Traspaso de la reserva: scr_fecres se apaga SIN devolver stock
            // (sto_reserv no se toca): desde acá la reserva es del pedido.
            $anterior   = (int) $solicitud->scr_escid;
            $convertida = EstadoSolicitud::porCodigo(EstadoSolicitud::CONVERTIDA);

            $solicitud->update([
                'scr_escid'  => $convertida->esc_id,
                'scr_fecres' => null,
            ]);

            SolicitudCreditoHistorial::create([
                'sch_scrid'  => $solicitud->scr_id,
                'sch_escant' => $anterior,
                'sch_escnue' => $convertida->esc_id,
                'sch_usuid'  => $usuario->usu_id,
                'sch_observ' => "Pedido {$pedido->ped_numero} generado: la reserva de stock pasó al pedido.",
            ]);

            return $pedido->fresh(['estadoPedido', 'estadoPago']);
        });
    }

    // ---------- Auxiliares ----------

    private function solicitudDelCliente(Usuario $usuario, int $id, bool $bloquear = false): SolicitudCredito
    {
        $cliente = $usuario->persona?->cliente;
        abort_if(! $cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        $consulta = SolicitudCredito::where('scr_cliid', $cliente->cli_id);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        // 404 si no existe o es de otro cliente: no se revela su existencia.
        return $consulta->with('estado')->findOrFail($id);
    }

    private function exigirListaParaCheckout(SolicitudCredito $solicitud): void
    {
        $codigo = $solicitud->estado?->esc_codigo;

        if ($codigo === EstadoSolicitud::CONVERTIDA) {
            abort(409, 'Esta solicitud ya generó su pedido.');
        }

        if ($codigo !== EstadoSolicitud::ACEPTADA) {
            abort(409, 'Para continuar tenés que aceptar primero las condiciones del crédito.');
        }

        if (! $solicitud->plazoVigente()) {
            abort(409, 'El plazo de esta solicitud venció el ' . $solicitud->scr_vighas?->format('d/m/Y')
                . ': ya no se puede completar el pedido.');
        }
    }

    /** El crédito se retira únicamente en casa matriz (ver StockCreditoService::resolverDeposito). */
    private function casaMatriz(): ?Sucursal
    {
        return Sucursal::where('suc_princi', 1)->with('ciudad')->first();
    }

    private function resolverFacturacion(Usuario $usuario, SolicitudCredito $solicitud, array $datos): array
    {
        $persona = $usuario->persona;

        if ($datos['tipo_documento'] === 'CI') {
            return [
                'tipo_documento' => 'CI',
                'dfi_id'         => null,
                'pfa_nrodoc'     => $persona->per_cedula ?: $solicitud->scr_cedula,
                'pfa_raznom'     => trim("{$solicitud->scr_nombre} {$solicitud->scr_apelli}"),
                'pfa_telefono'   => $solicitud->scr_celula,
                'pfa_correo'     => $usuario->usu_login,
                'pfa_recibe'     => $datos['pfa_recibe'],
            ];
        }

        // Se busca dentro de los datos fiscales de la propia persona:
        // un dfi_id ajeno responde 404.
        $dato = $persona->datosFiscales()->findOrFail($datos['dfi_id']);

        return [
            'tipo_documento' => 'RUC',
            'dfi_id'         => $dato->dfi_id,
            'pfa_nrodoc'     => $dato->dfi_numero,
            'pfa_raznom'     => $dato->dfi_razon,
            'pfa_telefono'   => $dato->dfi_telefono,
            'pfa_correo'     => $dato->dfi_correo,
            'pfa_recibe'     => $datos['pfa_recibe'],
        ];
    }

    private function crearDetalle(Pedido $pedido, SolicitudCredito $solicitud): void
    {
        foreach ($solicitud->detalles as $detalle) {
            $producto = $detalle->producto;

            DetallePedido::create([
                'dpd_pedid'      => $pedido->ped_id,
                'dpd_prdid'      => $detalle->scd_prdid,
                'dpd_descri'     => $producto?->publicacionWeb?->pub_titulo ?? $detalle->scd_descri,
                'dpd_cantid'     => $detalle->scd_cantid,
                // El precio unitario tiene que ser el FINANCIADO, no el de
                // contado: scd_preuni es el precio sin recargo y scd_subtot
                // ya lo incluye. Guardar los dos juntos deja la línea
                // incoherente (precio x cantidad != total) y la DNIT
                // rechaza la factura por esa diferencia.
                'dpd_precio'     => round((float) $detalle->scd_subtot / (float) $detalle->scd_cantid, 2),
                'dpd_subtot'     => $detalle->totalContado(),
                'dpd_total'      => $detalle->scd_subtot,
                'dpd_iva'        => $detalle->scd_iva,
                'dpd_modpag'     => 'credito',
                'dpd_numcuo'     => $detalle->scd_numcuo,
                'dpd_recargopct' => $detalle->scd_recpct,
                'dpd_montocuo'   => $detalle->scd_moncuo,
                'dpd_gardia'     => $producto
                    ? $producto->garantiaEfectivaDias()
                    : (int) config('servicio.garantia_compra_dias'),
            ]);
        }
    }

    private function crearEntrega(Pedido $pedido, SolicitudCredito $solicitud, Sucursal $sucursal, TipoEntrega $tipo): void
    {
        PedidoEntrega::create([
            'pen_pedid'   => $pedido->ped_id,
            'pen_tenid'   => $tipo->ten_id,
            'pen_sucid'   => $sucursal->suc_id,
            'pen_dirid'   => null,
            'pen_nrodoc'  => $solicitud->scr_cedula,
            'pen_nombre'  => $solicitud->scr_nombre,
            'pen_apelli'  => $solicitud->scr_apelli,
            'pen_telef'   => $solicitud->scr_celula,
            'pen_ciudad'  => $sucursal->ciudad?->ciu_descri,
        ]);
    }

    private function crearFacturacion(Pedido $pedido, array $f): void
    {
        PedidoFacturacion::create([
            'pfa_pedid'    => $pedido->ped_id,
            'pfa_tipodoc'  => $f['tipo_documento'],
            'pfa_dfiid'    => $f['dfi_id'],
            'pfa_nrodoc'   => $f['pfa_nrodoc'],
            'pfa_raznom'   => $f['pfa_raznom'],
            'pfa_telefono' => $f['pfa_telefono'],
            'pfa_correo'   => $f['pfa_correo'],
            'pfa_recibe'   => $f['pfa_recibe'],
        ]);
    }
}