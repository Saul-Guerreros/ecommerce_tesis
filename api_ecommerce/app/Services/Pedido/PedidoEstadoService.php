<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        EstadoPedido::PENDIENTE_PEDIDO      => [EstadoPedido::EN_PREPARACION, EstadoPedido::CANCELADO],
        EstadoPedido::EN_PREPARACION => [EstadoPedido::LISTO, EstadoPedido::CANCELADO],
        EstadoPedido::LISTO          => [EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO],
        EstadoPedido::ENTREGADO      => [],
        EstadoPedido::CANCELADO      => [],
    ];

    /**
     * Estados que exigen que el pedido este totalmente pagado.
     *
     * Esta guarda no esta en TRANSICIONES porque no depende del estado
     * del pedido sino del de los pagos: no se prepara lo que no esta
     * cobrado.
     */
    private const REQUIEREN_PAGO_CONFIRMADO = [EstadoPedido::EN_PREPARACION];

    public function __construct(
        private StockPedidoService $stock,
        private PlazoRetiroService $plazoRetiro,
        private \App\Services\Venta\VentaService $venta,   // ← NUEVO
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

        $pagoCumplido = $this->pagoCumplido($pedido);

        return EstadoPedido::whereIn('est_codigo', $destinos)
            ->where('est_estado', 1)
            ->orderBy('est_id')
            ->get()
            ->map(function (EstadoPedido $estado) use ($pagoCumplido) {
                $requierePago = in_array($estado->est_codigo, self::REQUIEREN_PAGO_CONFIRMADO, true);
                $bloqueado    = $requierePago && !$pagoCumplido;

                return [
                    'codigo'      => $estado->est_codigo,
                    'descripcion' => $estado->est_descri,
                    'habilitado'  => !$bloqueado,
                    'motivo'      => $bloqueado
                        ? 'Primero hay que confirmar todos los pagos del pedido.'
                        : null,
                ];
            })
            ->values()
            ->all();
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

            if (
                in_array($codigoDestino, self::REQUIEREN_PAGO_CONFIRMADO, true)
                && !$this->pagoCumplido($pedido)
            ) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede preparar un pedido cuyo pago todavía no está confirmado.',
                ]);
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
                // La garantia de la compra corre desde la entrega real.
                ...($codigoDestino === EstadoPedido::ENTREGADO ? ['ped_entreg' => now()] : []),
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
            $this->stock->descontarDefinitivo($pedido, $usuario);

            // Misma guarda que descontarDefinitivo(): si el pedido ya se
            // facturó al confirmar el pago, emitirDesdePedido() devuelve esa
            // factura y no consume un número nuevo.
            $this->venta->emitirDesdePedido($pedido, $usuario);

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
}