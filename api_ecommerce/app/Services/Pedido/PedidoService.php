<?php

namespace App\Services\Pedido;

use App\Models\Carrito\Carrito;
use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoEntrega;
use App\Models\Pedido\PedidoFacturacion;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creacion del pedido. Corresponde al Paso 2 del checkout (boton
 * "Confirmar Pedido"), NO al pago.
 *
 * Rediseno de octubre 2026 -- razon del cambio:
 *
 *   Antes, el cliente pagaba y subia su comprobante en el Paso 2, y el
 *   stock recien se reservaba en el Paso 3. Entre esos dos momentos otro
 *   cliente podia llevarse la ultima unidad, y el primero terminaba con
 *   una transferencia hecha contra un pedido que ya no se podia cumplir:
 *   el administrador tenia que devolver la plata a mano.
 *
 *   Ahora el pedido nace ANTES del pago. Al confirmarlo se toma la
 *   reserva (sto_reserv) y el pedido queda en PENDIENTE_PAGO con un
 *   vencimiento (ped_vence). Si no hay stock, el cliente se entera aca,
 *   sin haber pagado nada. El pago se declara despues, en el Paso 3,
 *   contra un pedido que ya existe (PagoPedidoService).
 *
 * El precio de invertir el orden es que un checkout abandonado deja stock
 * bloqueado: por eso existe ped_vence y el comando pedidos:expirar, que
 * cancela el pedido y devuelve la reserva.
 */
class PedidoService
{
    /**
     * Plazo que tiene el cliente para declarar su pago antes de que la
     * reserva se libere. Constante por ahora; si el negocio necesita
     * cambiarlo sin tocar codigo, el lugar natural es configuracion_tienda.
     */
    public const HORAS_PARA_PAGAR = 24;

    public function __construct(
        private CheckoutResumenService $resumen,
    ) {}

    // ---------- Paso 2: POST (Confirmar Pedido) ----------

    /**
     * Crea el pedido a partir del borrador (pedido_temporal) y reserva el
     * stock. No toca metodo de pago: ped_mpaid queda NULL y ped_epaid en
     * PENDIENTE_PAGO.
     *
     * Todo ocurre dentro de una transaccion con lockForUpdate() sobre las
     * filas de stock: dos clientes confirmando a la vez se serializan, y el
     * segundo recibe el error de stock sin haber pagado.
     */
    public function confirmar(Usuario $usuario): Pedido
    {
        $temporal     = $this->temporalCompleto($usuario);
        $carritoItems = $this->resumen->items($usuario);

        $cliente = $usuario->persona->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        $entrega     = $temporal->ptm_entrega;
        $facturacion = $temporal->ptm_factura;
        $resumen     = $this->resumen->resumenDeItems($carritoItems, $entrega);

        return DB::transaction(function () use (
            $usuario, $cliente, $carritoItems, $entrega, $facturacion, $resumen, $temporal
        ) {
            $pedido = Pedido::create([
                'ped_cliid'  => $cliente->cli_id,
                'ped_subtot' => $resumen['subtotal'],
                'ped_impues' => $resumen['impuestos'],
                'ped_cosenv' => $resumen['costo_envio'],
                'ped_total'  => $resumen['total'],
                'ped_tenid'  => $entrega['ten_id'],
                'ped_sucid'  => $entrega['suc_id'] ?? null,

                // El metodo de pago se define en el Paso 3. Con pago
                // dividido se queda en NULL de forma definitiva.
                'ped_mpaid'  => null,
                'ped_pagdiv' => false,

                'ped_estid'  => EstadoPedido::porCodigo(EstadoPedido::PENDIENTE_PEDIDO)->est_id,
                'ped_epaid'  => EstadoPago::porCodigo(EstadoPago::PENDIENTE_PAGO)->epa_id,
                'ped_vence'  => now()->addHours(self::HORAS_PARA_PAGAR),
            ]);

            // Numeración simple basada en el correlativo real del pedido.
            $pedido->update([
                'ped_numero' => 'PED-' . date('Y') . '-' . str_pad((string) $pedido->ped_id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->crearDetalle($pedido, $carritoItems);
            $this->crearEntrega($pedido, $entrega);
            $this->crearFacturacion($pedido, $facturacion);
            $this->reservarStock($carritoItems, $entrega);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $pedido->ped_id,
                'peh_usuid'  => $usuario->usu_id,
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => null,
                'peh_estid'  => $pedido->ped_estid,
                'peh_coment' => 'Pedido creado — stock reservado, pendiente de pago',
            ]);

            // El carrito al contado se vacia aca y no en el Paso 3: a partir
            // de este momento la verdad de la compra es el pedido, no el
            // carrito. Dejarlo lleno permitiria que el cliente confirmara el
            // mismo carrito dos veces y duplicara la reserva.
            //
            // El carrito a credito queda intacto: tiene su propio flujo.
            Carrito::where('car_idusu', $usuario->usu_id)
                ->where(function ($q) {
                    $q->whereNull('car_modpag')->orWhere('car_modpag', 'contado');
                })
                ->delete();

            $temporal->delete();

            return $pedido->fresh(['estadoPedido', 'estadoPago']);
        });
    }

    // ---------- Helpers ----------

    /**
     * El borrador ya NO necesita tener ptm_pago: el metodo de pago dejo de
     * ser un requisito para crear el pedido.
     */
    private function temporalCompleto(Usuario $usuario): PedidoTemporal
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();

        if (!$temporal || empty($temporal->ptm_entrega)) {
            throw ValidationException::withMessages(['entrega' => 'Completá primero los datos de entrega.']);
        }
        if (empty($temporal->ptm_factura)) {
            throw ValidationException::withMessages(['facturacion' => 'Completá primero los datos de facturación.']);
        }

        return $temporal;
    }

    private function crearDetalle(Pedido $pedido, Collection $carritoItems): void
    {
        foreach ($carritoItems as $item) {
            $producto    = $item->producto;
            $descripcion = $producto?->publicacionWeb?->pub_titulo ?? $producto?->prd_descri ?? 'Producto';

            DetallePedido::create([
                'dpd_pedid'      => $pedido->ped_id,
                'dpd_prdid'      => $item->car_idpro,
                'dpd_descri'     => $descripcion,
                'dpd_cantid'     => $item->car_cantid,
                'dpd_precio'     => $item->car_precio,
                'dpd_subtot'     => $item->car_subtot,
                'dpd_total'      => $item->car_total,
                'dpd_iva'        => $producto ? $producto->ivaIncluidoEn((float) $item->car_total) : 0,
                'dpd_modpag'     => $item->car_modpag ?? 'contado',
                'dpd_numcuo'     => $item->car_numcuo,
                'dpd_recargopct' => $item->car_recargopct,
                'dpd_montocuo'   => $item->car_montocuo,
                // Snapshot de la garantía: si el plazo del producto cambia
                // despues, esta compra conserva el que se le ofrecio.
                'dpd_gardia'     => $producto
                    ? $producto->garantiaEfectivaDias()
                    : (int) config('servicio.garantia_compra_dias'),
            ]);
        }
    }

    private function crearEntrega(Pedido $pedido, array $e): void
    {
        PedidoEntrega::create([
            'pen_pedid'      => $pedido->ped_id,
            'pen_tenid'      => $e['ten_id'],
            'pen_sucid'      => $e['suc_id'] ?? null,
            'pen_dirid'      => $e['dir_id'] ?? null,
            'pen_nrodoc'     => $e['rec_nrodoc'],
            'pen_nombre'     => $e['rec_nombre'],
            'pen_apelli'     => $e['rec_apelli'],
            'pen_telef'      => $e['rec_telef'] ?? null,
            'pen_departa'    => $e['departamento'] ?? null,
            'pen_ciudad'     => $e['ciudad'] ?? ($e['suc_ciudad'] ?? null),
            'pen_barrio'     => $e['barrio'] ?? null,
            'pen_calle'      => $e['calle'] ?? null,
            'pen_nrocasa'    => $e['nrocasa'] ?? null,
            'pen_referencia' => $e['referencia'] ?? null,
        ]);
    }

    private function crearFacturacion(Pedido $pedido, array $f): void
    {
        PedidoFacturacion::create([
            'pfa_pedid'    => $pedido->ped_id,
            'pfa_tipodoc'  => $f['tipo_documento'],
            'pfa_dfiid'    => $f['dfi_id'] ?? null,
            'pfa_nrodoc'   => $f['pfa_nrodoc'],
            'pfa_raznom'   => $f['pfa_raznom'],
            'pfa_telefono' => $f['pfa_telefono'] ?? null,
            'pfa_correo'   => $f['pfa_correo'] ?? null,
            'pfa_recibe'   => $f['pfa_recibe'],
        ]);
    }

    /**
     * Reserva (sto_reserv), no descuenta (sto_cantid): el descuento
     * definitivo y el movimiento_stock 'S' quedan para StockPedidoService,
     * cuando el admin confirma el pago.
     *
     * lockForUpdate() es lo que resuelve el problema original: dos
     * confirmaciones simultaneas sobre la ultima unidad se ponen en fila, y
     * la segunda falla ANTES de que ese cliente haya pagado.
     */
    private function reservarStock(Collection $carritoItems, array $entrega): void
    {
        $deposito = $this->resolverDeposito($entrega);

        foreach ($carritoItems as $item) {
            $producto = $item->producto;

            if (!$producto || !$producto->prd_afestk) {
                continue; // producto que no controla stock
            }

            abort_if(!$deposito, 422, 'No se pudo determinar el depósito de despacho.');

            $stock = Stock::where('sto_prdid', $producto->prd_id)
                ->where('sto_depid', $deposito->dpo_id)
                ->lockForUpdate()
                ->first();

            $disponible = $stock ? ((float) $stock->sto_cantid - (float) $stock->sto_reserv) : 0.0;

            if ($disponible < (float) $item->car_cantid) {
                throw ValidationException::withMessages([
                    'stock' => "No hay stock suficiente de \"{$producto->prd_descri}\" para completar el pedido.",
                ]);
            }

            $stock->increment('sto_reserv', (float) $item->car_cantid);
        }
    }

    /**
     * Resolución simplificada del depósito de despacho: el principal de la
     * sucursal de retiro elegida, o el de la sucursal principal de la
     * empresa cuando es envío a domicilio. Tiene que coincidir con el
     * criterio de StockPedidoService, que despues libera esa misma reserva.
     */
    private function resolverDeposito(array $entrega): ?Deposito
    {
        $sucursalId = $entrega['suc_id'] ?? Sucursal::where('suc_princi', 1)->value('suc_id');

        if (!$sucursalId) {
            return null;
        }

        return Deposito::where('dpo_sucid', $sucursalId)->where('dpo_princi', 1)->first();
    }
}