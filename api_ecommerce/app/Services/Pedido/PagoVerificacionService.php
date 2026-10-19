<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verificacion de los pagos de un pedido: la accion mas sensible del
 * modulo, porque toca dinero y stock a la vez.
 *
 * Regla central del pago dividido: cada pago se verifica por separado,
 * pero el stock se descuenta y el pedido avanza UNA SOLA VEZ, cuando el
 * ultimo pago pendiente queda confirmado. Descontar por pago parcial
 * obligaria a revertir inventario si el segundo comprobante se rechaza.
 */
class PagoVerificacionService
{
    public function __construct(
        private StockPedidoService $stock,
        private PedidoEstadoService $estados,
        private EstadoPagoPedidoService $estadoPago,
        private \App\Services\Venta\VentaService $venta,   // ← NUEVO
    ) {}

    // ---------- Confirmar ----------

    /**
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string, confirmar_diferencia: ?bool} $datos
     */
    public function confirmar(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $pago = $this->bloquearPago($pedido, $pagoId);

            $this->validarPedidoOperable($pedido);
            $this->validarTransicionDePago($pago, EstadoPago::CONFIRMADO);
            $this->validarComprobante($pago);
            $this->validarMonto($pago, $datos);

            $pago->update([
                'ppa_epaid'   => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
                'ppa_montver' => $datos['monto_verificado'],
                'ppa_nrotrx'  => $datos['nro_transaccion'] ?? null,
                'ppa_banco'   => $datos['banco'] ?? null,
                'ppa_fechpag' => $datos['fecha_pago'] ?? null,
                'ppa_observ'  => $datos['observacion'] ?? null,
                'ppa_mrpid'   => null,   // un pago confirmado no conserva motivo de rechazo
                'ppa_usuver'  => $usuario->usu_id,
                'ppa_fechver' => now(),
            ]);

            $pago->comprobante?->update(['pco_verifi' => true]);

            $this->registrarEnHistorial(
                $pedido,
                $usuario,
                sprintf(
                    'Pago %d confirmado — Gs. %s',
                    $pago->ppa_orden,
                    number_format((float) $datos['monto_verificado'], 0, ',', '.')
                ),
                EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
            );

            $quedoTotalmentePagado = $this->estadoPago->recalcular($pedido);

            if ($quedoTotalmentePagado) {
                $this->cerrarCobro($pedido, $usuario);
            }

            return $pedido->refresh();
        });
    }

    /**
     * Efectos que se disparan una unica vez, cuando el pedido entero queda
     * cobrado. Cualquiera de los tres que falle revierte toda la operacion:
     * el pago no queda confirmado.
     */
    private function cerrarCobro(Pedido $pedido, Usuario $usuario): void
    {
        // 1. Descuento definitivo de stock + kardex.
        //    Lanza StockInsuficienteException (409) si ya no hay existencias.
        $this->stock->descontarDefinitivo($pedido, $usuario);

        // 2. Avance automatico del pedido. Pasa por la misma maquina de
        //    estados que el combo manual, no la saltea.
        $this->estados->cambiar(
            $pedido->refresh(),
            EstadoPedido::EN_PREPARACION,
            $usuario,
            'Pago confirmado — el pedido pasa a preparación automáticamente',
        );

        // 3. Emisión del comprobante fiscal. Consume un correlativo del
        //    timbrado con lock pesimista, dentro de esta misma transacción:
        //    si la emisión falla (timbrado vencido, rango agotado), el pago
        //    NO queda confirmado y el stock no se descuenta. Es lo correcto:
        //    una venta cerrada sin factura es peor que un pago que hay que
        //    reintentar después de cargar el talonario.
        $this->venta->emitirDesdePedido($pedido->refresh(), $usuario);
    }

    // ---------- Rechazar ----------

    /**
     * @param array{motivo_id: int, detalle: ?string, monto_verificado: ?float} $datos
     */
    public function rechazar(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $pago = $this->bloquearPago($pedido, $pagoId);

            $this->validarPedidoOperable($pedido);
            $this->validarTransicionDePago($pago, EstadoPago::RECHAZADO);

            $motivo = MotivoRechazoPago::activos()->findOrFail($datos['motivo_id']);

            if ($motivo->mrp_requdet && blank($datos['detalle'] ?? null)) {
                throw ValidationException::withMessages([
                    'detalle' => 'Este motivo exige un detalle para el cliente.',
                ]);
            }

            $pago->update([
                'ppa_epaid'   => EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
                'ppa_mrpid'   => $motivo->mrp_id,
                'ppa_observ'  => $datos['detalle'] ?? $motivo->mrp_descri,
                'ppa_montver' => $datos['monto_verificado'] ?? null,
                'ppa_usuver'  => $usuario->usu_id,
                'ppa_fechver' => now(),
            ]);

            // El comprobante vuelve a "no verificado": el cliente deberá
            // subir uno nuevo para este mismo pago.
            $pago->comprobante?->update(['pco_verifi' => false]);

            $this->registrarEnHistorial(
                $pedido,
                $usuario,
                "Pago {$pago->ppa_orden} rechazado — {$motivo->mrp_descri}",
                EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
            );

            $this->estadoPago->recalcular($pedido);
            $this->estadoPago->reabrirPlazo($pedido);

            return $pedido->refresh();
        });
    }

    // ---------- Validaciones ----------

    /**
     * SELECT ... FOR UPDATE sobre la fila del pago. Sin esto, dos
     * administradores confirmando el mismo pago a la vez podrian ejecutar
     * el descuento de stock dos veces.
     */
    private function bloquearPago(Pedido $pedido, int $pagoId): PedidoPago
    {
        $pago = PedidoPago::where('ppa_id', $pagoId)
            ->where('ppa_pedid', $pedido->ped_id)
            ->lockForUpdate()
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a este pedido.');

        return $pago->load(['estadoPago', 'metodoPago', 'comprobante']);
    }

    private function validarPedidoOperable(Pedido $pedido): void
    {
        if ($pedido->estadoPedido?->est_codigo === EstadoPedido::CANCELADO) {
            throw ValidationException::withMessages([
                'pago' => 'El pedido está cancelado: sus pagos ya no se pueden verificar.',
            ]);
        }
    }

    private function validarTransicionDePago(PedidoPago $pago, string $destino): void
    {
        $actual = $pago->estadoPago?->epa_codigo;

        if ($actual === $destino) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya se encuentra en ese estado.',
            ]);
        }

        // Revertir un pago ya confirmado implicaria devolver stock y, si ya
        // hubo factura, emitir una nota de credito. Es un proceso aparte,
        // no un simple cambio de estado.
        if ($actual === EstadoPago::CONFIRMADO) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya fue confirmado. Para revertirlo hay que registrar un reembolso.',
            ]);
        }
    }

    private function validarComprobante(PedidoPago $pago): void
    {
        if ($pago->metodoPago?->mpa_requcom && !$pago->comprobante) {
            throw ValidationException::withMessages([
                'pago' => "El método \"{$pago->metodoPago->mpa_descri}\" exige comprobante y este pago no tiene ninguno cargado.",
            ]);
        }
    }

    /**
 * Un pago POR DEBAJO del monto asignado no se puede confirmar: dejaría
 * el pedido como pagado cuando el cliente todavía debe la diferencia.
 * Ese caso corresponde rechazar el pago para que suba un comprobante
 * nuevo que cubra el faltante.
 *
 * Un pago POR ENCIMA sí puede ser legítimo (transferencia de más,
 * redondeo bancario), así que se permite con confirmación explícita del
 * administrador y la diferencia queda registrada.
 */
private function validarMonto(PedidoPago $pago, array $datos): void
{
    $esperado   = (float) $pago->ppa_monto;
    $verificado = (float) $datos['monto_verificado'];

    if (abs($esperado - $verificado) < 0.01) {
        return;
    }

    if ($verificado < $esperado) {
        throw ValidationException::withMessages([
            'monto_verificado' => sprintf(
                'El comprobante cubre Gs. %s de los Gs. %s asignados. Faltan Gs. %s: el pago debe rechazarse para que el cliente complete la diferencia.',
                number_format($verificado, 0, ',', '.'),
                number_format($esperado, 0, ',', '.'),
                number_format($esperado - $verificado, 0, ',', '.')
            ),
        ]);
    }

    // Pago en exceso: permitido con confirmación.
    if (empty($datos['confirmar_diferencia'])) {
        throw ValidationException::withMessages([
            'monto_verificado' => sprintf(
                'El comprobante excede en Gs. %s el monto asignado. Confirmá el excedente para continuar.',
                number_format($verificado - $esperado, 0, ',', '.')
            ),
        ]);
    }
}

    private function registrarEnHistorial(
        Pedido $pedido,
        Usuario $usuario,
        string $comentario,
        int $estadoId,
    ): void {
        PedidoHistorialEstado::create([
            'peh_pedid'  => $pedido->ped_id,
            'peh_usuid'  => $usuario->usu_id,
            'peh_tipo'   => 'PAGO',
            'peh_estant' => $pedido->ped_epaid,
            'peh_estid'  => $estadoId,
            'peh_coment' => $comentario,
            'peh_ip'     => request()->ip(),
        ]);
    }
}