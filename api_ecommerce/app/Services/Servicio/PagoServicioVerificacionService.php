<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verificación de los pagos WEB del servicio (bandeja "Cobros de servicios"
 * y detalle de la solicitud). Espejo de PagoVerificacionService del pedido,
 * con el mismo permiso pedidos.pago.verificar (decisión 14-b).
 *
 * Diferencia de fondo con el pedido: confirmar el último pago NO dispara
 * stock ni factura. El stock del servicio ya se descontó al cerrar la OT y
 * la facturación es una instrucción posterior. Solo habilita la entrega (A5).
 *
 * El efectivo "pagar al retirar" no se confirma acá: lo confirma el cajero
 * al cobrarlo (CobroMostradorService::confirmarAlRetirar), para que quede
 * quién cobró y en qué sucursal.
 */
class PagoServicioVerificacionService
{
    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private NotificadorServicio $notificador,
    ) {}

    /**
     * @param array{monto_verificado: float, nro_transaccion: ?string, banco: ?string,
     *              fecha_pago: ?string, observacion: ?string, confirmar_diferencia: ?bool} $datos
     */
    public function confirmar(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            $s    = $this->bloquearSolicitud($s);
            $pago = $this->bloquearPago($s, $pagoId);

            $this->validarOperable($s);
            $this->validarPendiente($pago);

            if ($pago->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA) {
                throw ValidationException::withMessages([
                    'pago' => 'El efectivo se confirma en el mostrador al cobrarlo ("Registrar cobro").',
                ]);
            }

            if ($pago->metodoPago?->mpa_requcom && !$pago->comprobante) {
                throw ValidationException::withMessages([
                    'pago' => "El método \"{$pago->metodoPago->mpa_descri}\" exige comprobante y este pago no tiene ninguno cargado.",
                ]);
            }

            $this->validarMonto($pago, $datos);

            $pago->update([
                'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::CONFIRMADO)->epa_id,
                'spa_montver' => $datos['monto_verificado'],
                'spa_nrotrx'  => $datos['nro_transaccion'] ?? null,
                'spa_banco'   => $datos['banco'] ?? null,
                'spa_fechpag' => $datos['fecha_pago'] ?? null,
                'spa_observ'  => $datos['observacion'] ?? null,
                'spa_mrpid'   => null,
                'spa_usuver'  => $usuario->usu_id,
                'spa_fechver' => now(),
            ]);

            $pago->comprobante?->update(['sco_verifi' => true]);

            $this->estadoPago->recalcular($s, $usuario->usu_id, sprintf(
                'Pago %d confirmado — Gs. %s',
                $pago->spa_orden,
                number_format((float) $datos['monto_verificado'], 0, ',', '.'),
            ));

            return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
        });
    }

    /**
     * @param array{motivo_id: int, detalle: ?string, monto_verificado: ?float} $datos
     */
    public function rechazar(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            $s    = $this->bloquearSolicitud($s);
            $pago = $this->bloquearPago($s, $pagoId);

            $this->validarOperable($s);
            $this->validarPendiente($pago);

            $motivo = MotivoRechazoPago::activos()->findOrFail($datos['motivo_id']);

            if ($motivo->mrp_requdet && blank($datos['detalle'] ?? null)) {
                throw ValidationException::withMessages(['detalle' => 'Este motivo exige un detalle para el cliente.']);
            }

            $detalle = $datos['detalle'] ?? $motivo->mrp_descri;

            $pago->update([
                'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
                'spa_mrpid'   => $motivo->mrp_id,
                'spa_observ'  => $detalle,
                'spa_montver' => $datos['monto_verificado'] ?? null,
                'spa_usuver'  => $usuario->usu_id,
                'spa_fechver' => now(),
            ]);

            $pago->comprobante?->update(['sco_verifi' => false]);

            $this->estadoPago->recalcular($s, $usuario->usu_id,
                "Pago {$pago->spa_orden} rechazado — {$motivo->mrp_descri}");

            // Solo se avisa si hay algo que el cliente pueda corregir (comprobante).
            if ($pago->metodoPago?->mpa_requcom) {
                $this->notificador->alCambiarPago($s, EstadoPago::RECHAZADO, $detalle);
            }

            return $s->fresh(['pagos.metodoPago', 'pagos.estadoPago', 'pagos.comprobante', 'estadoPago']);
        });
    }

    // ---------- Validaciones ----------

    private function bloquearSolicitud(SolicitudServicio $s): SolicitudServicio
    {
        return SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with('estadoServicio')->firstOrFail();
    }

    /** SELECT ... FOR UPDATE: dos verificadores sobre el mismo pago se serializan. */
    private function bloquearPago(SolicitudServicio $s, int $pagoId): ServicioPago
    {
        $pago = ServicioPago::where('spa_id', $pagoId)
            ->where('spa_sseid', $s->sse_id)
            ->lockForUpdate()
            ->first();

        abort_if(!$pago, 404, 'El pago no pertenece a esta solicitud.');

        return $pago->load(['estadoPago', 'metodoPago', 'comprobante']);
    }

    private function validarOperable(SolicitudServicio $s): void
    {
        if ($s->codigoServicio() === EstadoServicio::CANCELADA) {
            throw ValidationException::withMessages(['pago' => 'La solicitud está cancelada: sus pagos ya no se pueden verificar.']);
        }
    }

    /**
     * Solo se verifica lo que está esperando verificación. Un confirmado no
     * se revierte (sería un reembolso) y un rechazado espera al cliente.
     */
    private function validarPendiente(ServicioPago $pago): void
    {
        $actual = $pago->estadoPago?->epa_codigo;

        if ($actual !== EstadoPago::PAGO_VERIFICAR) {
            throw ValidationException::withMessages([
                'pago' => match ($actual) {
                    EstadoPago::CONFIRMADO => 'Este pago ya fue confirmado. Para revertirlo hay que registrar un reembolso.',
                    EstadoPago::RECHAZADO  => 'Este pago ya fue rechazado: espera un comprobante nuevo del cliente.',
                    default                => 'Este pago no está en verificación.',
                },
            ]);
        }
    }

    /** Mismo criterio que el pedido: de menos se rechaza, de más se confirma explícitamente. */
    private function validarMonto(ServicioPago $pago, array $datos): void
    {
        $esperado   = (float) $pago->spa_monto;
        $verificado = (float) $datos['monto_verificado'];

        if (abs($esperado - $verificado) < 0.01) {
            return;
        }

        $gs = fn ($m) => number_format($m, 0, ',', '.');

        if ($verificado < $esperado) {
            throw ValidationException::withMessages([
                'monto_verificado' => "El comprobante cubre Gs. {$gs($verificado)} de los Gs. {$gs($esperado)} asignados. "
                    . "Faltan Gs. {$gs($esperado - $verificado)}: el pago debe rechazarse para que el cliente complete la diferencia.",
            ]);
        }

        if (empty($datos['confirmar_diferencia'])) {
            throw ValidationException::withMessages([
                'monto_verificado' => "El comprobante excede en Gs. {$gs($verificado - $esperado)} el monto asignado. Confirmá el excedente para continuar.",
            ]);
        }
    }
}
