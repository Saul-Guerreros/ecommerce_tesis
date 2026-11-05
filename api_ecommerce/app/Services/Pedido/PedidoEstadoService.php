<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Pedido\PagoCheque;

use App\Models\Configuracion\ConfiguracionTienda;

/**
 * Maquina de estados del pedido.
 *
 * Toda la regla vive en un solo array y en un solo metodo. La alternativa
 * (ifs repartidos por el controlador) llevaria a que una pantalla permita
 * una transicion que otra bloquea, y a que el combo del panel y el
 * backend terminen desincronizados: aca el combo se construye con
 * transicionesPermitidas() y la validacion usa exactamente lo mismo.
 */
class PedidoEstadoService
{
    /**
     * Grafo de transiciones. ENTREGADO y CANCELADO son terminales: un
     * pedido entregado que se devuelve NO vuelve atras, se resuelve con
     * una nota de credito.
     */
    private const TRANSICIONES = [
        // LISTO directo desde PENDIENTE_PEDIDO es el camino del cheque:
        // la mercadería ya está en la sucursal, así que "En preparación"
        // no describe ningún trabajo real. La guarda de abajo impide que
        // ese atajo se use en un pedido sin cheque recibido.
        EstadoPedido::PENDIENTE_PEDIDO => [EstadoPedido::EN_PREPARACION, EstadoPedido::LISTO, EstadoPedido::CANCELADO],
        EstadoPedido::EN_PREPARACION   => [EstadoPedido::LISTO, EstadoPedido::CANCELADO],
        EstadoPedido::LISTO            => [EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO],
        EstadoPedido::ENTREGADO        => [],
        EstadoPedido::CANCELADO        => [],
    ];

    /**
     * Estados que exigen que el pedido este totalmente pagado.
     *
     * Esta guarda no esta en TRANSICIONES porque no depende del estado
     * del pedido sino del de los pagos: no se prepara lo que no esta
     * cobrado.
     */
    private const REQUIEREN_PAGO_CONFIRMADO = [EstadoPedido::EN_PREPARACION];

        /**
     * Estados que exigen que TODOS los pagos esten cobrados.
     *
     * Es mas estricto que REQUIEREN_PAGO_CONFIRMADO: ahi alcanza con que
     * esten cobrados los pagos con comprobante, porque el efectivo entra
     * recien al entregar. Aca ya no hay un despues: la mercaderia sale
     * del local, asi que el efectivo tiene que estar registrado.
     */
    private const REQUIEREN_COBRO_COMPLETO = [EstadoPedido::ENTREGADO];

    public function __construct(
        private StockPedidoService $stock,
        private PlazoRetiroService $plazoRetiro,
       
    ) {}

    /**
     * Opciones que el combo «Cambiar estado» debe ofrecer, cada una con
     * el motivo por el que esta deshabilitada (null si esta habilitada).
     *
     * @return array<int, array{codigo: string, descripcion: string, habilitado: bool, motivo: ?string}>
     */
        public function transicionesPermitidas(Pedido $pedido): array
    {
        $actual   = $pedido->estadoPedido?->est_codigo;
        $destinos = self::TRANSICIONES[$actual] ?? [];

        if (empty($destinos)) {
            return [];
        }

        $pagoCumplido    = $this->pagoCumplido($pedido);
        $cobroCompleto   = $this->cobroCompleto($pedido);
        $facturado       = $this->tieneFactura($pedido);
        $actualCodigo    = $actual;
        $chequesListos   = $this->chequesRecibidos($pedido);
        $esperaAcredit   = $this->esperaAcreditacionCheque($pedido);

        return EstadoPedido::whereIn('est_codigo', $destinos)
            ->where('est_estado', 1)
            ->orderBy('est_id')
            ->get()
            ->map(function (EstadoPedido $estado) use ($pagoCumplido, $cobroCompleto, $facturado, $actualCodigo, $chequesListos, $esperaAcredit) {
                $motivo = $this->motivoDeBloqueo(
                    $estado->est_codigo,
                    $pagoCumplido,
                    $cobroCompleto,
                    $facturado,
                    $actualCodigo,
                    $chequesListos,
                    $esperaAcredit,
                );

                return [
                    'codigo'      => $estado->est_codigo,
                    'descripcion' => $estado->est_descri,
                    'habilitado'  => $motivo === null,
                    'motivo'      => $motivo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Un unico lugar decide por que una transicion esta bloqueada, y tanto
     * el combo como la validacion lo consultan. Si el motivo viviera dos
     * veces, el panel y el backend terminarian discrepando.
     */
          private function motivoDeBloqueo(
        string $codigoDestino,
        bool $pagoCumplido,
        bool $cobroCompleto,
        bool $facturado,
        ?string $codigoActual = null,
        bool $chequesListos = false,
        bool $esperaAcreditacion = false,
    ): ?string {
        // El atajo PENDIENTE_PEDIDO -> LISTO existe sólo para el cheque.
        if ($codigoDestino === EstadoPedido::LISTO
            && $codigoActual === EstadoPedido::PENDIENTE_PEDIDO
            && !$chequesListos) {
            return 'Este pedido todavía no tiene el cheque recibido en la sucursal.';
        }

        // Política ACREDITACION: con un cheque sin acreditar el pedido no
        // queda listo. Lo libera el registro del cobro, no un cambio manual.
        if ($codigoDestino === EstadoPedido::LISTO && $esperaAcreditacion) {
            return 'El cheque todavía no fue acreditado: el pedido queda listo cuando se registre el cobro.';
        }

        if (in_array($codigoDestino, self::REQUIEREN_PAGO_CONFIRMADO, true) && !$pagoCumplido) {
            return 'Primero hay que cobrar todos los pagos con comprobante del pedido.';
        }

        if (in_array($codigoDestino, self::REQUIEREN_COBRO_COMPLETO, true) && !$cobroCompleto) {
            return 'Falta registrar el cobro: el pedido no se entrega sin la plata registrada.';
        }

        if ($codigoDestino === EstadoPedido::CANCELADO && $facturado) {
            return 'El pedido ya tiene factura declarada: la cancelación requiere nota de crédito.';
        }

        return null;
    }  

    /** El pedido tiene cheques y todos llegaron a la sucursal. */
    private function chequesRecibidos(Pedido $pedido): bool
    {
        $pedido->loadMissing('pagos.cheque');

        $cheques = $pedido->pagos->pluck('cheque')->filter();

        return $cheques->isNotEmpty()
            && $cheques->every(fn (PagoCheque $c) => $c->fueRecibido());
    }

    

    /**
     * Aplica el cambio de estado. Abre su propia transaccion: no se puede
     * asumir que todo caller la abrio (el combo manual del admin no lo
     * hacia), y ahora esta operacion tambien puede tocar stock. Si se
     * invoca desde dentro de otra transaccion (PagoVerificacionService),
     * Laravel la anida con un savepoint sin problema.
     */
    public function cambiar(
        Pedido $pedido,
        string $codigoDestino,
        ?Usuario $usuario = null,
        ?string $comentario = null,
    ): Pedido {
        return DB::transaction(function () use ($pedido, $codigoDestino, $usuario, $comentario) {
            $actual = $pedido->estadoPedido?->est_codigo;

            if ($actual === $codigoDestino) {
                throw ValidationException::withMessages([
                    'estado' => 'El pedido ya se encuentra en ese estado.',
                ]);
            }

            if (!in_array($codigoDestino, self::TRANSICIONES[$actual] ?? [], true)) {
                throw ValidationException::withMessages([
                    'estado' => "No se puede pasar de \"{$pedido->estadoPedido?->est_descri}\" a ese estado.",
                ]);
            }

                $motivo = $this->motivoDeBloqueo(
                $codigoDestino,
                $this->pagoCumplido($pedido),
                $this->cobroCompleto($pedido),
                $this->tieneFactura($pedido),
                esperaAcreditacion: $this->esperaAcreditacionCheque($pedido),
            );

            if ($motivo !== null) {
                throw ValidationException::withMessages(['estado' => $motivo]);
            }

            // Efecto sobre stock, ANTES de mover el estado: si algo falla
            // aca (sin stock, caso borde de cancelacion), el estado no
            // tiene que quedar a medio camino.
            $this->aplicarEfectoDeStock($pedido, $codigoDestino, $usuario);

            $destino       = EstadoPedido::porCodigo($codigoDestino);
            $estadoAnterior = $pedido->ped_estid;

            $pedido->update([
                'ped_estid'   => $destino->est_id,
                ...$this->camposDePlazoRetiro($pedido, $codigoDestino),
            ]);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $pedido->ped_id,
                'peh_usuid'  => $usuario?->usu_id,
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => $estadoAnterior,
                'peh_estid'  => $destino->est_id,
                'peh_coment' => $comentario ?? "Estado → {$destino->est_descri}",
                'peh_ip'     => request()->ip(),
            ]);

            return $pedido->refresh();
        });
    }

    /**
     * Los tres puntos donde este modulo debe tocar stock, segun el
     * documento de maquinas de estado:
     *
     *   - ENTREGADO: descuenta si todavia no se desconto. Cubre contra
     *     entrega puro (nunca se descuenta antes) y pago dividido (la
     *     parte con comprobante ya se descuento en cerrarCobro(), la
     *     parte en efectivo recien se cobra aca) de una sola vez, gracias
     *     a la guarda del kardex en descontarDefinitivo().
     *
     *   - CANCELADO: libera la reserva, salvo que el pedido ya haya
     *     salido del deposito de verdad. Ese caso borde (cancelar algo ya
     *     descontado) necesitaria devolver stock real y, si ya hubo
     *     factura, una nota de credito — eso no lo puede resolver un
     *     cambio de estado, requiere intervencion explicita y queda fuera
     *     de esta instruccion.
     */
    /**
     * El reloj del retiro arranca al pasar a LISTO y se apaga al salir de
     * ahí (ENTREGADO o CANCELADO).
     *
     * Arranca en LISTO y no antes porque el cliente no puede retirar algo
     * que todavía no está armado: contarlo desde la confirmación le
     * descontaría del plazo el tiempo que la tienda tardó en preparar.
     *
     * Se apaga al salir porque un ped_vencret vivo en un pedido ya
     * entregado haría que la bandeja lo pintara de rojo para siempre.
     *
     * En envío a domicilio devuelve null: no hay nada que ir a buscar.
     */
    private function camposDePlazoRetiro(Pedido $pedido, string $codigoDestino): array
    {
        if ($codigoDestino === EstadoPedido::LISTO) {
            return ['ped_vencret' => $this->plazoRetiro->calcularParaPedido($pedido)];
        }

        if (in_array($codigoDestino, [EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO], true)) {
            return ['ped_vencret' => null];
        }

        return [];
    }

        private function aplicarEfectoDeStock(Pedido $pedido, string $codigoDestino, ?Usuario $usuario): void
    {
        if ($codigoDestino === EstadoPedido::ENTREGADO) {
            // Solo stock. Ni factura ni cobro: para llegar aca los dos ya
            // tienen que estar hechos (motivoDeBloqueo lo exige), y en
            // contra entrega el cobro se registra desde la tarjeta Pago.
            $this->stock->descontarDefinitivo($pedido, $usuario);

            return;
        }

        if ($codigoDestino === EstadoPedido::CANCELADO) {
            if ($this->stock->yaDescontado($pedido)) {
                throw ValidationException::withMessages([
                    'estado' => 'Este pedido ya descontó stock real del depósito: no se puede cancelar '
                        . 'con un simple cambio de estado. Hace falta devolver el stock y, si ya hubo '
                        . 'factura, emitir una nota de crédito (funcionalidad pendiente).',
                ]);
            }

            $this->stock->liberarReserva($pedido);
        }
    }

    /**
     * El pago "está cumplido" para efectos de preparar el pedido cuando
     * TODO pago que exige comprobante (ppa_metid.mpa_requcom) ya está
     * CONFIRMADO. Los pagos que no exigen comprobante (contra entrega)
     * no cuentan para esta condición: su cobro recién ocurre al entregar.
     *
     * Esto no es lo mismo que mirar estado_pago === CONFIRMADO: un
     * pedido con pago dividido (transferencia + efectivo contra entrega)
     * puede quedar en PARCIAL hasta la entrega, porque el efectivo nunca
     * llega a CONFIRMADO antes de ese momento — y aun así ya está listo
     * para prepararse en cuanto la parte con comprobante quedó validada.
     * Si se exigiera CONFIRMADO a secas, ese pedido mixto quedaría
     * trabado igual que el contra entrega puro.
     */
    private function pagoCumplido(Pedido $pedido): bool
    {
        $pedido->loadMissing('pagos.metodoPago', 'pagos.estadoPago');

        return $pedido->pagos
            ->filter(fn ($pago) => (bool) $pago->metodoPago?->mpa_requcom)
            ->every(fn ($pago) => $pago->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO);
    }

       /**
     * TODOS los pagos cobrados, salvo el cheque recibido cuando la política
     * es RECEPCION.
     *
     * Con RECEPCION el cheque en la sucursal basta para entregar: el
     * riesgo del cheque rebotado lo asume la tienda. Con ACREDITACION no:
     * el pedido ni siquiera llega a LISTO hasta que el banco acredite, que
     * es justamente lo que ese criterio protege.
     *
     * El pedido sí pasa a LISTO en ambos casos: la factura sólo se puede
     * generar desde LISTO y el cobro se aplica contra la factura, así que
     * retener también ese estado dejaría el flujo en un círculo.
     */
    private function cobroCompleto(Pedido $pedido): bool
    {
        $pedido->loadMissing('pagos.estadoPago', 'pagos.cheque');

        $chequeBasta = ConfiguracionTienda::actual()->chequeDescuentaEnRecepcion();

        return $pedido->pagos->every(
            fn ($pago) => $pago->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO
                || ($chequeBasta && $pago->cheque?->fueRecibido() === true)
        );
    }

        /**
     * Política ACREDITACION y al menos un cheque todavía sin cobrar.
     * Se consulta fresco a la base: se usa justo después de registrar un
     * cobro, cuando las relaciones ya cargadas podrían estar desactualizadas.
     */
    private function esperaAcreditacionCheque(Pedido $pedido): bool
    {
        if (ConfiguracionTienda::actual()->chequeDescuentaEnRecepcion()) {
            return false;
        }

        return $pedido->pagos()
            ->with('estadoPago', 'cheque')
            ->get()
            ->contains(fn ($pago) => $pago->cheque !== null
                && $pago->estadoPago?->epa_codigo !== EstadoPago::CONFIRMADO);
    }

    private function tieneFactura(Pedido $pedido): bool
    {
        return $pedido->factura()->exists();
    }
}