<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use App\Models\Venta\VentaCab;
use App\Services\Venta\CobroVentaService;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\Configuracion\ConfiguracionTienda;

use App\Models\Pedido\PagoCheque;

/**
 * Verificacion de los pagos de un pedido: la accion mas sensible del
 * modulo, porque toca dinero y stock a la vez.
 *
 * Desde el ciclo "factura -> cobro" son DOS acciones separadas, en este
 * orden:
 *
 *   1. validarComprobante() -- el admin compara el comprobante con el
 *      extracto del banco y lo da por bueno. No mueve plata ni stock: solo
 *      habilita el paso siguiente. Es lo unico que puede ocurrir antes de
 *      que exista un documento fiscal.
 *
 *   2. registrarCobro() -- recien aca entra la plata: se registra un
 *      cobro_venta contra la factura del pedido.
 *
 * Por que separarlas: la factura se declara a la DNIT con el monto
 * declarado y no se corrige con un cambio de estado, solo con nota de
 * credito. Validar primero el comprobante es lo que evita declarar un
 * comprobante que despues resulta ser falso o por otro importe.
 *
 * Regla central del pago dividido: cada pago se cobra por separado, pero
 * el stock se descuenta y el pedido avanza UNA SOLA VEZ, cuando el ultimo
 * pago pendiente queda cobrado. Descontar por pago parcial obligaria a
 * revertir inventario si el segundo comprobante se rechaza.
 */
class PagoVerificacionService
{
    public function __construct(
        private StockPedidoService $stock,
        private PedidoEstadoService $estados,
        private EstadoPagoPedidoService $estadoPago,
        private CobroVentaService $cobros,
    ) {}

    // ---------- 1. Validar el comprobante ----------

    /**
     * Marca el comprobante como validado y deja asentado quien lo miro,
     * cuando y que observo.
     *
     * No cambia el estado del pago: el pago sigue pendiente hasta que se
     * registre el cobro. Son dos hechos distintos y la pantalla los
     * muestra como dos tarjetas distintas.
     *
     * @param array{observacion: ?string, confirma_monto: bool} $datos
     */
    public function validarComprobante(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $pago = $this->bloquearPago($pedido, $pagoId);

            $this->validarPedidoOperable($pedido);

            $estado = $pago->estadoPago?->epa_codigo;

            if ($estado === EstadoPago::CONFIRMADO) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este pago ya fue cobrado: su comprobante no se vuelve a validar.',
                ]);
            }

            if ($estado === EstadoPago::RECHAZADO) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este pago está rechazado. Hay que esperar a que el cliente suba un comprobante nuevo.',
                ]);
            }

            $comprobante = $pago->comprobante;

            if (!$comprobante) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este pago no tiene ningún comprobante cargado para validar.',
                ]);
            }

            if ($comprobante->pco_verifi) {
                throw ValidationException::withMessages([
                    'comprobante' => 'Este comprobante ya fue validado.',
                ]);
            }

            $comprobante->update([
                'pco_verifi'  => true,
                'pco_observ'  => $datos['observacion'] ?? null,
                'pco_usuver'  => $usuario->usu_id,
                'pco_fechver' => now(),
            ]);

            $this->registrarEnHistorial(
                $pedido,
                $usuario,
                sprintf(
                    'Comprobante del pago %d validado — cubre Gs. %s',
                    $pago->ppa_orden,
                    number_format((float) $pago->ppa_monto, 0, ',', '.')
                ),
                $pedido->ped_epaid,
            );

            return $pedido->refresh();
        });
    }

    // ---------- 2. Registrar el cobro ----------

    /**
     * Registra la entrada de dinero contra la factura del pedido.
     *
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string, confirmar_diferencia: ?bool} $datos
     */
    public function registrarCobro(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $pago = $this->bloquearPago($pedido, $pagoId);

            $this->validarPedidoOperable($pedido);
            $this->validarTransicionDePago($pago, EstadoPago::CONFIRMADO);
            $this->validarComprobanteValidado($pago);

            $this->validarPedidoOperable($pedido);
            $this->validarTransicionDePago($pago, EstadoPago::CONFIRMADO);
            $this->validarComprobanteValidado($pago);
            $this->validarChequeRecibido($pago);      // <-- nueva
            $this->validarMonto($pago, $datos);

            $this->validarMonto($pago, $datos);

            // La factura tiene que existir ANTES del cobro: el cobro se
            // aplica contra un documento fiscal, no contra el pedido.
            $factura = $this->facturaParaCobrar($pedido);

            $this->cobros->registrar($factura, $pago, $datos, $usuario);

            // Las columnas ppa_* de verificacion ya no se escriben: ese dato
            // ahora vive en cobro_venta. Del pago solo cambia su estado.
            $pago->update([
                'ppa_epaid' => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
                'ppa_mrpid' => null,   // un pago cobrado no conserva motivo de rechazo
            ]);
            // El cheque cierra su ciclo acá: el banco pagó. Se actualiza en
            // la misma transacción que el cobro para que los dos datos no
            // puedan quedar desfasados.
            if ($pago->cheque && !$pago->cheque->fueAcreditado()) {
                $pago->cheque->update(['pch_estado' => PagoCheque::ACREDITADO]);
            }

            $this->registrarEnHistorial(
                $pedido,
                $usuario,
                sprintf(
                    'Cobro del pago %d registrado — Gs. %s (factura %s)',
                    $pago->ppa_orden,
                    number_format((float) $datos['monto_verificado'], 0, ',', '.'),
                    $factura->ven_nrodoc
                ),
                EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
            );

            $quedoTotalmentePagado = $this->estadoPago->recalcular($pedido);

            if ($quedoTotalmentePagado || $this->chequeAcreditadoLibera($pedido)) {
                $this->cerrarPedido($pedido, $usuario);
            }

            return $pedido->refresh();
        });
    }

       /**
     * Factura sobre la que se aplica el cobro. Tiene que existir: la genera
     * el administrador con [GENERAR FACTURA] despues de validar los
     * comprobantes.
     *
     * Este service ya no emite nada. Cobrar y facturar son decisiones
     * distintas, y el orden entre ellas es justamente lo que el ciclo
     * factura -> cobro vino a ordenar.
     */
    private function facturaParaCobrar(Pedido $pedido): VentaCab
    {
        $factura = $pedido->factura()->lockForUpdate()->first();

        if (!$factura) {
            throw ValidationException::withMessages([
                'cobro' => 'Este pedido todavía no tiene factura. Generala antes de registrar el cobro.',
            ]);
        }

        return $factura;
    }

        /**
     * Política ACREDITACION con pago mixto (cheque + efectivo contra
     * entrega): el efectivo recién se cobra al entregar, así que el pedido
     * nunca queda "totalmente pagado" antes de salir. Sin esta regla,
     * acreditar el cheque no descontaría stock ni dejaría el pedido listo.
     *
     * Libera cuando todo pago que se verifica antes de entregar (cheque o
     * comprobante) ya está cobrado.
     */
    private function chequeAcreditadoLibera(Pedido $pedido): bool
    {
        if (ConfiguracionTienda::actual()->chequeDescuentaEnRecepcion()) {
            return false;
        }

        $pagos = $pedido->pagos()->with('metodoPago', 'estadoPago', 'cheque')->get();

        if ($pagos->pluck('cheque')->filter()->isEmpty()) {
            return false;
        }

        return $pagos
            ->filter(fn ($pago) => $pago->metodoPago?->requiereVerificacion())
            ->every(fn ($pago) => $pago->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO);
    }

    /**
     * Efectos que se disparan una unica vez, cuando el pedido entero queda
     * cobrado. Cualquiera de los dos que falle revierte toda la operacion:
     * el cobro no queda registrado.
     *
     * La factura no se toca aca: ya existe desde antes del primer cobro.
     */
        /**
     * Efectos que se disparan una única vez, cuando el pedido entero queda
     * cobrado.
     *
     * Con cheque, buena parte de esto ya ocurrió antes: con política
     * RECEPCION el stock se descontó al recibir el papel y el pedido ya
     * pasó a LISTO (o incluso a ENTREGADO). Por eso los dos efectos se
     * aplican de forma condicional en vez de incondicional: repetirlos
     * dejaría el inventario descontado dos veces y haría fallar una
     * transición hacia un estado anterior.
     */
    private function cerrarPedido(Pedido $pedido, Usuario $usuario): void
    {
        if (!$this->stock->yaDescontado($pedido)) {
            $this->stock->descontarDefinitivo($pedido, $usuario);
        }

        $actual = $pedido->estadoPedido?->est_codigo;

        if (in_array($actual, [EstadoPedido::LISTO, EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO], true)) {
            return;   // el cheque ya lo adelantó; no hay nada que avanzar
        }

        $pedido->loadMissing('pagos.cheque');
        $hayCheque = $pedido->pagos->pluck('cheque')->filter()->isNotEmpty();

        $this->estados->cambiar(
            $pedido->refresh(),
            $hayCheque ? EstadoPedido::LISTO : EstadoPedido::EN_PREPARACION,
            $usuario,
            $hayCheque
                ? 'Cheque acreditado — el pedido queda listo para entrega/retiro'
                : 'Pago cobrado — el pedido pasa a preparación automáticamente',
        );
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

            $this->validarSinFactura($pedido);
            
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

            // El comprobante vuelve a "no validado" y se borra la validacion
            // anterior: el cliente debera subir uno nuevo para este pago y
            // ese comprobante nuevo tiene que validarse desde cero.
            $pago->comprobante?->update([
                'pco_verifi'  => false,
                'pco_observ'  => null,
                'pco_usuver'  => null,
                'pco_fechver' => null,
            ]);

            $this->registrarEnHistorial(
                $pedido,
                $usuario,
                "Pago {$pago->ppa_orden} rechazado — {$motivo->mrp_descri}",
                EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
            );

                $this->estadoPago->recalcular($pedido);

            if ($this->cancelarPorRechazos($pedido, $usuario)) {
                return $pedido->refresh();
            }

            $this->estadoPago->reabrirPlazo($pedido);

            return $pedido->refresh();
        });
    }

        /**
     * Tercer rechazo: el pedido se cancela solo y libera la reserva.
     *
     * El contador vive en el PEDIDO y no en el pago: lo que se agota es la
     * paciencia con el pedido entero, no con una forma de pago.
     *
     * La excepcion es el pedido que ya tiene algun pago cobrado. Ahi hay
     * plata real del cliente en la cuenta de la tienda, y cancelar
     * obligaria a devolverla; se lo deja abierto para que siga subiendo
     * comprobantes hasta que uno sea correcto.
     *
     * @return bool true si el pedido quedo cancelado.
     */
    private function cancelarPorRechazos(Pedido $pedido, Usuario $usuario): bool
    {
        $pedido->increment('ped_rechaz');
        $pedido->refresh();

        if ($pedido->ped_rechaz < Pedido::MAX_RECHAZOS_PAGO) {
            return false;
        }

        if ($pedido->tienePagoConfirmado()) {
            return false;
        }

        $this->estados->cambiar(
            $pedido,
            EstadoPedido::CANCELADO,
            $usuario,
            sprintf(
                'Cancelado automáticamente: %d comprobantes rechazados',
                $pedido->ped_rechaz
            ),
        );

        return true;
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

        /**
     * Con factura declarada, el rechazo deja de ser una opcion: la DNIT ya
     * tiene el comprobante y la unica correccion posible es una nota de
     * credito.
     */
    private function validarSinFactura(Pedido $pedido): void
    {
        $factura = $pedido->factura;

        if ($factura) {
            throw ValidationException::withMessages([
                'pago' => "El pedido ya tiene la factura {$factura->ven_nrodoc} declarada. "
                    . 'Un pago de un pedido facturado no se rechaza: corresponde nota de crédito.',
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

        // Revertir un pago ya cobrado implicaria devolver stock y, como la
        // factura ya esta declarada, emitir una nota de credito. Es un
        // proceso aparte, no un simple cambio de estado.
        if ($actual === EstadoPago::CONFIRMADO) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago ya fue cobrado. Para revertirlo hay que anular el cobro y registrar un reembolso.',
            ]);
        }
    }

    /**
     * No se cobra lo que no se miro. Es la guarda que hace cumplir el orden
     * comprobante -> factura -> cobro para cada pago por separado.
     */
    private function validarComprobanteValidado(PedidoPago $pago): void
    {
        if (!$pago->metodoPago?->mpa_requcom) {
            return;   // contra entrega: no hay comprobante que validar
        }

        if (!$pago->comprobante) {
            throw ValidationException::withMessages([
                'cobro' => "El método \"{$pago->metodoPago->mpa_descri}\" exige comprobante y este pago no tiene ninguno cargado.",
            ]);
        }

        if (!$pago->comprobante->pco_verifi) {
            throw ValidationException::withMessages([
                'cobro' => 'Primero validá el comprobante de este pago. El cobro se registra después.',
            ]);
        }
    }

        private function validarChequeRecibido(PedidoPago $pago): void
    {
        $pago->loadMissing('cheque');

        if ($pago->cheque && !$pago->cheque->fueRecibido()) {
            throw ValidationException::withMessages([
                'cobro' => 'El cheque de este pago todavía no fue recibido en la sucursal. '
                    . 'Confirmá la recepción antes de registrar el cobro.',
            ]);
        }
    }

    /** Comprobantes del pedido que todavia nadie valido. */
    private function comprobantesSinValidar(Pedido $pedido): int
    {
        $pedido->loadMissing('pagos.metodoPago', 'pagos.comprobante');

        return $pedido->pagos
            ->filter(fn ($pago) => (bool) $pago->metodoPago?->mpa_requcom)
            ->filter(fn ($pago) => !$pago->comprobante?->pco_verifi)
            ->count();
    }

    /**
     * Un pago POR DEBAJO del monto asignado no se puede cobrar: dejaria el
     * pedido como pagado cuando el cliente todavia debe la diferencia. Ese
     * caso corresponde rechazar el pago para que suba un comprobante nuevo
     * que cubra el faltante.
     *
     * Un pago POR ENCIMA si puede ser legitimo (transferencia de mas,
     * redondeo bancario), asi que se permite con confirmacion explicita del
     * administrador y la diferencia queda registrada en el cobro.
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
        ?int $estadoId,
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