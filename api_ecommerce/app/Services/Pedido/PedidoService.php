<?php

namespace App\Services\Pedido;

use App\Http\Resources\Carrito\CarritoResource;
use App\Models\Carrito\Carrito;
use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\DetallePedido;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoComprobante;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoEntrega;
use App\Models\Pedido\PedidoFacturacion;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoPago;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function __construct(
        private CheckoutResumenService $resumen,
    ) {}

    // ---------- Paso 3: GET (resumen) ----------

    public function datosConfirmacion(Usuario $usuario): array
    {
        $temporal = $this->temporalCompleto($usuario);
        $carritoItems = $this->resumen->items($usuario);

        return [
            'items'       => CarritoResource::collection($carritoItems),
            'entrega'     => $temporal->ptm_entrega,
            'facturacion' => $temporal->ptm_factura,
            'pago'        => $this->pagoConMontos($temporal, $carritoItems),
            'resumen'     => $this->resumen->resumenDeItems($carritoItems, $temporal->ptm_entrega),
        ];
    }

    // ---------- Paso 3: POST (Confirmar Pedido) ----------

    public function confirmar(Usuario $usuario): Pedido
    {
        $temporal = $this->temporalCompleto($usuario);
        $carritoItems = $this->resumen->items($usuario);

        $cliente = $usuario->persona->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        $entrega     = $temporal->ptm_entrega;
        $facturacion = $temporal->ptm_factura;
        $resumen     = $this->resumen->resumenDeItems($carritoItems, $entrega);

        // Entre el Paso 2 y el Paso 3 el carrito pudo cambiar (precio, cantidad,
        // costo de envio). Los montos se revalidan aca contra el total recien
        // calculado: sin esto se podria confirmar un pedido cuyos pagos no lo
        // cubren.
        $pago   = $this->pagoConMontos($temporal, $carritoItems);
        $metodo = $this->metodoPrincipal($pago);

        return DB::transaction(function () use (
            $usuario, $cliente, $carritoItems, $entrega, $facturacion, $pago, $metodo, $resumen, $temporal
        ) {
            $pedido = Pedido::create([
                'ped_cliid'  => $cliente->cli_id,
                'ped_subtot' => $resumen['subtotal'],
                'ped_impues' => $resumen['impuestos'],
                'ped_cosenv' => $resumen['costo_envio'],
                'ped_total'  => $resumen['total'],
                'ped_tenid'  => $entrega['ten_id'],
                'ped_sucid'  => $entrega['suc_id'] ?? null,
                'ped_mpaid'  => $metodo?->mpa_id,                  // null cuando el pago es dividido
                'ped_pagdiv' => count($pago['pagos']) > 1,
                'ped_estid'  => EstadoPedido::porCodigo(EstadoPedido::PENDIENTE)->est_id,
                'ped_epaid'  => EstadoPago::porCodigo(EstadoPago::PENDIENTE)->epa_id,
            ]);

            // Numeración simple basada en el correlativo real del pedido.
            $pedido->update([
                'ped_numero' => 'PED-' . date('Y') . '-' . str_pad((string) $pedido->ped_id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->crearDetalle($pedido, $carritoItems);
            $this->crearEntrega($pedido, $entrega);
            $this->crearFacturacion($pedido, $facturacion);
            $this->crearPagos($pedido, $pago['pagos']);
            $this->reservarStock($carritoItems, $entrega);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $pedido->ped_id,
                'peh_usuid'  => $usuario->usu_id,
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => null,
                'peh_estid'  => $pedido->ped_estid,
                'peh_coment' => count($pago['pagos']) > 1
                    ? 'Pedido creado con pago dividido en ' . count($pago['pagos']) . ' pagos'
                    : 'Pedido creado',
            ]);

            // Los comprobantes NO se borran de disco: pago_comprobante.pco_archiv
            // pasa a ser su referencia definitiva.

            // Solo se vacia la modalidad contado: el carrito a credito
            // queda intacto para su propio flujo.
            Carrito::where('car_idusu', $usuario->usu_id)
                ->where(function ($q) {
                    $q->whereNull('car_modpag')->orWhere('car_modpag', 'contado');
                })
                ->delete();

            $temporal->delete();

            return $pedido->fresh();
        });
    }

    // ---------- Pagos ----------

    /**
     * Devuelve el borrador de pago normalizado y con los montos resueltos
     * contra el total vigente.
     *
     * - Pago unico: el monto es siempre el total, se recalcula aca (tambien
     *   cubre los borradores viejos, que no guardaban monto).
     * - Pago dividido: la suma debe coincidir exactamente con el total.
     */
    private function pagoConMontos(PedidoTemporal $temporal, Collection $carritoItems): array
    {
        $pago  = $temporal->pagoNormalizado();
        $total = (int) round($this->resumen->resumenDeItems($carritoItems, $temporal->ptm_entrega)['total']);

        if (count($pago['pagos']) === 1) {
            $pago['pagos'][0]['monto'] = $total;

            return $pago;
        }

        $asignado = array_sum(array_map(fn ($p) => (int) ($p['monto'] ?? 0), $pago['pagos']));

        if ($asignado !== $total) {
            throw ValidationException::withMessages([
                'pagos' => 'El total de tu pedido cambió (Gs. ' . number_format($total, 0, ',', '.')
                    . '). Volvé al paso de pago y ajustá los montos.',
            ]);
        }

        return $pago;
    }

    /**
     * Metodo "principal" de pedido.ped_mpaid: solo existe cuando el cliente
     * pago con uno solo. Con pago dividido la verdad esta en pedido_pago.
     */
    private function metodoPrincipal(array $pago): ?MetodoPago
    {
        return count($pago['pagos']) === 1
            ? MetodoPago::porCodigo($pago['pagos'][0]['mpa_codigo'])
            : null;
    }

    /**
     * Una fila de pedido_pago por cada pago declarado, siempre: con un solo
     * metodo se escribe una sola fila. Cada comprobante temporal se
     * convierte en una fila de pago_comprobante ligada a su pago.
     */
    private function crearPagos(Pedido $pedido, array $pagos): void
    {
        $pendiente = EstadoPago::porCodigo(EstadoPago::PENDIENTE)->epa_id;

        foreach (array_values($pagos) as $i => $p) {
            $metodo = MetodoPago::porCodigo($p['mpa_codigo']);

            $pedidoPago = PedidoPago::create([
                'ppa_pedid' => $pedido->ped_id,
                'ppa_orden' => $i + 1,
                'ppa_mpaid' => $metodo->mpa_id,
                'ppa_monto' => $p['monto'],
                'ppa_epaid' => $pendiente,
            ]);

            if ($metodo->mpa_requcom && !empty($p['comprobante_temp'])) {
                PagoComprobante::create([
                    'pco_pedid'  => $pedido->ped_id,
                    'pco_ppaid'  => $pedidoPago->ppa_id,
                    'pco_archiv' => $p['comprobante_temp'],
                    'pco_verifi' => false,
                ]);
            }
        }
    }

    // ---------- Helpers ----------

    private function temporalCompleto(Usuario $usuario): PedidoTemporal
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuario->usu_id)->first();

        if (!$temporal || empty($temporal->ptm_entrega)) {
            throw ValidationException::withMessages(['entrega' => 'Completá primero los datos de entrega.']);
        }
        if (empty($temporal->ptm_factura)) {
            throw ValidationException::withMessages(['facturacion' => 'Completá primero los datos de facturación.']);
        }
        if (empty($temporal->ptm_pago)) {
            throw ValidationException::withMessages(['pago' => 'Completá primero el método de pago.']);
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
     * definitivo y el movimiento_stock 'S' quedan para PagoService, cuando
     * el admin confirma el pago (fuera del alcance de este paso).
     * lockForUpdate() evita que dos confirmaciones simultáneas sobrevendan
     * la última unidad.
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
     * empresa cuando es envío a domicilio. La resolución multi-sucursal
     * por stock y tarifa (Parte 2 del checkout) queda para una etapa
     * posterior — mismo TODO ya anotado en CheckoutEntregaService.
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