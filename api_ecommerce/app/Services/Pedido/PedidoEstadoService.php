<?php

namespace App\Services\Pedido;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Models\Usuario;
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
        EstadoPedido::PENDIENTE      => [EstadoPedido::EN_PREPARACION, EstadoPedido::CANCELADO],
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

        $pagoConfirmado = $pedido->estadoPago?->epa_codigo === EstadoPago::CONFIRMADO;

        return EstadoPedido::whereIn('est_codigo', $destinos)
            ->where('est_estado', 1)
            ->orderBy('est_id')
            ->get()
            ->map(function (EstadoPedido $estado) use ($pagoConfirmado) {
                $requierePago = in_array($estado->est_codigo, self::REQUIEREN_PAGO_CONFIRMADO, true);
                $bloqueado    = $requierePago && !$pagoConfirmado;

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
     * Aplica el cambio de estado. Asume que quien llama ya abrio la
     * transaccion (PagoVerificacionService lo invoca dentro de la suya).
     */
    public function cambiar(
        Pedido $pedido,
        string $codigoDestino,
        ?Usuario $usuario = null,
        ?string $comentario = null,
    ): Pedido {
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
            && $pedido->estadoPago?->epa_codigo !== EstadoPago::CONFIRMADO
        ) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede preparar un pedido cuyo pago todavía no está confirmado.',
            ]);
        }

        $destino       = EstadoPedido::porCodigo($codigoDestino);
        $estadoAnterior = $pedido->ped_estid;

        $pedido->update(['ped_estid' => $destino->est_id]);

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
    }
}