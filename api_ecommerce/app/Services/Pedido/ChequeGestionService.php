<?php

namespace App\Services\Pedido;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Pedido\PagoChequeCambio;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo del cheque físico dentro de Gestión de Pedidos.
 *
 * Es el equivalente de PagoVerificacionService para el método que no
 * lleva archivo, y existe aparte por una diferencia de fondo: en la
 * transferencia la evidencia y el dinero llegan juntos, mientras que el
 * cheque suma dos pasos que la transferencia no tiene — la recepción
 * física del papel y, mucho después, su acreditación bancaria.
 *
 * Consecuencia directa: el descuento de stock deja de colgar del cobro.
 * Como la entrega ocurre ANTES de que el banco acredite, esperar al cobro
 * dejaría salir mercadería sin descontarla del inventario. Por eso el
 * disparador pasa a ser configurable (ConfiguracionTienda::ctn_cheque_stock)
 * y, por defecto, es la recepción.
 *
 * Cuatro operaciones, todas sobre un pago concreto:
 *   - actualizarDatos()     [Editar]
 *   - descartar()           [Descartar cheque]
 *   - confirmarRecepcion()  [Confirmar recepción]
 *   - rechazar()            [Rechazar cheque]
 */
class ChequeGestionService
{
    public function __construct(
        private StockPedidoService $stock,
        private PedidoEstadoService $estados,
        private PagoVerificacionService $verificacion,
    ) {}

    // ---------- Editar los datos declarados ----------

    /**
     * Corrige los datos del cheque contra el papel que tiene el admin en
     * la mano. Un cheque DESCARTADO vuelve a EN_ESPERA: cargar los datos
     * correctos es, justamente, lo que lo reactiva.
     *
     * @param array{tipo:string, banco:string, nombre:string, nrodoc:string,
     *              nro:string, fecha_cobro:?string} $datos
     */
    public function actualizarDatos(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $cheque = $this->bloquearCheque($pedido, $pagoId);

            $this->validarEditable($cheque);

            PagoChequeCambio::registrar($cheque, PagoChequeCambio::EDICION, $usuario->usu_id);

            $cheque->update([
                'pch_tipo'   => $datos['tipo'],
                'pch_banco'  => $datos['banco'],
                'pch_nombre' => $datos['nombre'],
                'pch_nrodoc' => $datos['nrodoc'],
                'pch_nro'    => $datos['nro'],
                // La fecha de cobro sólo existe en el diferido: si el admin
                // corrigió el tipo a VISTA, la fecha vieja deja de tener
                // sentido y se limpia en el mismo acto.
                'pch_fecobr' => $datos['tipo'] === PagoCheque::TIPO_DIFERIDO
                    ? ($datos['fecha_cobro'] ?? null)
                    : null,
                'pch_estado' => PagoCheque::EN_ESPERA,
            ]);

            $this->historial(
                $pedido,
                $usuario,
                "Datos del cheque del pago {$cheque->pago->ppa_orden} corregidos por el administrador",
            );

            return $pedido->refresh();
        });
    }

    // ---------- Descartar ----------

    /**
     * El cliente presentó un cheque completamente distinto del declarado.
     *
     * Borra la declaración del checkout y deja el cheque esperando los
     * datos correctos. No cancela el pedido ni toca el plazo: el papel
     * está en la sucursal, sólo hay que registrarlo bien.
     *
     * @param array{motivo: ?string} $datos
     */
    public function descartar(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $cheque = $this->bloquearCheque($pedido, $pagoId);

            if ($cheque->pch_estado !== PagoCheque::EN_ESPERA) {
                throw ValidationException::withMessages([
                    'cheque' => 'Sólo se puede descartar un cheque que todavía está en espera.',
                ]);
            }

            PagoChequeCambio::registrar(
                $cheque,
                PagoChequeCambio::DESCARTE,
                $usuario->usu_id,
                $datos['motivo'] ?? null,
            );

            $cheque->update([
                'pch_banco'  => null,
                'pch_nombre' => null,
                'pch_nrodoc' => null,
                'pch_nro'    => null,
                'pch_fecobr' => null,
                'pch_estado' => PagoCheque::DESCARTADO,
            ]);

            $this->historial(
                $pedido,
                $usuario,
                "Cheque del pago {$cheque->pago->ppa_orden} descartado"
                    . (blank($datos['motivo'] ?? null) ? '' : " — {$datos['motivo']}"),
            );

            return $pedido->refresh();
        });
    }

    // ---------- Confirmar recepción ----------

    /**
     * El papel está en la sucursal y coincide con lo registrado.
     *
     * Este es el punto del flujo que reemplaza al cobro como disparador
     * del inventario: con política RECEPCION, acá se descuenta el stock y
     * el pedido pasa a Listo para entrega/retiro. No pasa por "En
     * preparación" porque en una venta con retiro en sucursal esa etapa no
     * describe ningún trabajo real: la mercadería ya está en el local.
     *
    * @param array{confirma_monto: bool, observacion: ?string} $datos
     */
    public function confirmarRecepcion(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $cheque = $this->bloquearCheque($pedido, $pagoId);

            if ($cheque->fueRecibido()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Este cheque ya figura como recibido.',
                ]);
            }

            if (!$cheque->estaPendiente()) {
                throw ValidationException::withMessages([
                    'cheque' => "Un cheque en estado \"{$cheque->descripcionEstado()}\" ya no se puede recibir.",
                ]);
            }

            if (!$cheque->datosCompletos()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Faltan datos del cheque. Cargá banco, titular, CI, número y '
                        . '—si es diferido— la fecha de cobro antes de confirmar la recepción.',
                ]);
            }

            $cheque->update([
                'pch_estado' => PagoCheque::RECIBIDO,
                // Siempre la hora del servidor: no se acepta una fecha enviada por el cliente.
                'pch_frecep' => now(),
                'pch_usurec' => $usuario->usu_id,
                'pch_motdet' => $datos['observacion'] ?? null,
            ]);

            $this->historial(
                $pedido,
                $usuario,
                sprintf(
                    'Cheque %s del pago %d recibido — Gs. %s',
                    $cheque->pch_nro,
                    $cheque->pago->ppa_orden,
                    number_format((float) $cheque->pago->ppa_monto, 0, ',', '.'),
                ),
            );

            $this->avanzarSiTodosRecibidos($pedido->refresh(), $usuario);

            return $pedido->refresh();
        });
    }

    // ---------- Rechazar ----------
    /**
     * El papel llegó pero no sirve, o nunca llegó dentro del plazo.
     *
     * A diferencia del comprobante, acá el rechazo es terminal: el cliente
     * no tiene en «Mis pedidos» ninguna pantalla para declarar otro cheque,
     * así que dejar el pedido abierto lo condenaría a quedar colgado hasta
     * que lo barra el vencimiento. Se cancela en el acto y se libera la
     * reserva, igual que el tercer rechazo de una transferencia. El cliente
     * puede volver a comprar.
     *
     * El rechazo del PAGO se delega en PagoVerificacionService para no
     * duplicar reglas que ya viven ahí (prohibición con factura declarada,
     * contador de rechazos, recálculo del estado agregado).
     *
     * @param array{motivo_id:int, detalle: ?string} $datos
     */
    public function rechazar(Pedido $pedido, int $pagoId, array $datos, Usuario $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $pagoId, $datos, $usuario) {
            $cheque = $this->bloquearCheque($pedido, $pagoId);

            if ($cheque->fueRecibido()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Este cheque ya fue recibido. Si rebotó después, corresponde '
                        . 'anular el cobro, no rechazar la recepción.',
                ]);
            }

            $motivo = MotivoRechazoPago::activos()->findOrFail($datos['motivo_id']);

            if (!$motivo->aplicaA(MotivoRechazoPago::AMBITO_CHEQUE)) {
                throw ValidationException::withMessages([
                    'motivo_id' => 'Ese motivo no corresponde al rechazo de un cheque.',
                ]);
            }

            $cheque->update([
                'pch_estado' => PagoCheque::RECHAZADO,
                'pch_mrpid'  => $motivo->mrp_id,
                'pch_motdet' => $datos['detalle'] ?? $motivo->mrp_descri,
                'pch_fevcto' => null,   // el reloj de 48 h deja de correr
            ]);

            $this->verificacion->rechazar($pedido, $pagoId, [
                'motivo_id'        => $motivo->mrp_id,
                'detalle'          => $datos['detalle'] ?? null,
                'monto_verificado' => null,
            ], $usuario);

            $pedido->refresh()->load('estadoPedido');

            // Si el contador de rechazos todavía no llegó al tope, el
            // service anterior dejó el pedido abierto. Para el cheque eso
            // no aplica: se cancela ahora. El cambio de estado es el que
            // libera la reserva (PedidoEstadoService::aplicarEfectoDeStock).
            if ($pedido->estadoPedido?->est_codigo !== EstadoPedido::CANCELADO) {
                $pedido->update(['ped_vence' => null]);

                $this->estados->cambiar(
                    $pedido->refresh(),
                    EstadoPedido::CANCELADO,
                    $usuario,
                    "Cheque rechazado — {$motivo->mrp_descri}",
                );
            }

            return $pedido->refresh();
        });
    }

    // ---------- Reglas internas ----------

       /**
     * El pedido avanza una sola vez, cuando TODOS sus cheques están
     * recibidos. Con pago dividido en dos cheques, recibir el primero no
     * habilita nada: descontar stock ahí obligaría a devolverlo si el
     * segundo nunca llega.
     *
     * Con política ACREDITACION recibir el papel NO libera nada: el pedido
     * sigue en "Pedido Recibido" y el stock sigue reservado. Pasa a LISTO y
     * descuenta stock recién cuando se registra el cobro (ver
     * PagoVerificacionService::cerrarPedido). Marcarlo LISTO antes diría
     * "retirá tu pedido" a un cliente que todavía no puede retirarlo.
     */
    private function avanzarSiTodosRecibidos(Pedido $pedido, Usuario $usuario): void
    {
        $cheques = $this->chequesDelPedido($pedido);

        if ($cheques->isEmpty() || $cheques->contains(fn (PagoCheque $c) => !$c->fueRecibido())) {
            return;
        }

        if (!ConfiguracionTienda::actual()->chequeDescuentaEnRecepcion()) {
            return;
        }

        if (!$this->stock->yaDescontado($pedido)) {
            $this->stock->descontarDefinitivo($pedido, $usuario);
        }

        $actual = $pedido->estadoPedido?->est_codigo;

        if (in_array($actual, [EstadoPedido::LISTO, EstadoPedido::ENTREGADO, EstadoPedido::CANCELADO], true)) {
            return;
        }

        $this->estados->cambiar(
            $pedido,
            EstadoPedido::LISTO,
            $usuario,
            'Cheque recibido en sucursal — el pedido queda listo para entrega/retiro',
        );
    }

    /** @return \Illuminate\Support\Collection<int, PagoCheque> */
    private function chequesDelPedido(Pedido $pedido)
    {
        return PagoCheque::where('pch_pedid', $pedido->ped_id)
            ->where('pch_estado', '!=', PagoCheque::RECHAZADO)
            ->get();
    }

    /**
     * SELECT ... FOR UPDATE sobre el cheque. Dos administradores
     * confirmando la misma recepción a la vez podrían descontar stock dos
     * veces.
     */
    private function bloquearCheque(Pedido $pedido, int $pagoId): PagoCheque
    {
        $pago = PedidoPago::where('ppa_id', $pagoId)
            ->where('ppa_pedid', $pedido->ped_id)
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a este pedido.');

        if ($pedido->estadoPedido?->est_codigo === EstadoPedido::CANCELADO) {
            throw ValidationException::withMessages([
                'cheque' => 'El pedido está cancelado: sus cheques ya no se gestionan.',
            ]);
        }

        $cheque = PagoCheque::where('pch_ppaid', $pagoId)->lockForUpdate()->first();

        abort_if(!$cheque, 404, 'Este pago no se realizó con cheque.');

        $cheque->setRelation('pago', $pago);

        return $cheque;
    }

    private function validarEditable(PagoCheque $cheque): void
    {
        if (!$cheque->estaPendiente()) {
            throw ValidationException::withMessages([
                'cheque' => "Un cheque en estado \"{$cheque->descripcionEstado()}\" ya no se edita.",
            ]);
        }
    }

    /**
     * Eje CHEQUE del historial. peh_estid guarda el estado de PAGO vigente
     * porque la columna es NOT NULL y apunta a ese catálogo; el detalle
     * del cheque va en el comentario.
     */
    private function historial(Pedido $pedido, Usuario $usuario, string $comentario): void
    {
        PedidoHistorialEstado::create([
            'peh_pedid'  => $pedido->ped_id,
            'peh_usuid'  => $usuario->usu_id,
            'peh_tipo'   => 'CHEQUE',
            'peh_estant' => $pedido->ped_epaid,
            'peh_estid'  => $pedido->ped_epaid,
            'peh_coment' => $comentario,
            'peh_ip'     => request()->ip(),
        ]);
    }
}