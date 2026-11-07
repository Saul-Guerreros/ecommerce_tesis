<?php

namespace App\Services\Servicio;

use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelación de una solicitud hasta antes de LISTA (decisión de negocio).
 *
 *   - Equipo recibido: se registra su DEVOLUCIÓN en el mismo acto
 *     (entrega_servicio, ese_tipo DEVOLUCION), sin cobro.
 *   - Equipo no recibido: se cancela directo. En tránsito no se cancela:
 *     primero se registra la recepción.
 *   - Stock: antes de LISTA no hubo salida (EjecucionServicioService, regla 1),
 *     solo reservas: se liberan.
 *   - Anticipo sin resolver o factura final vigente: se bloquea.
 *
 * CANCELADA es para cuando NO hay nada que cobrar. Si hubo trabajo que se
 * quiere cobrar, se usa el cierre existente (no reparable / finalizar) y
 * después la entrega.
 */
class CancelacionServicioService
{
    public const CANCELABLES = [
        EstadoServicio::SOLICITADA,
        EstadoServicio::RECIBIDA,
        EstadoServicio::EN_DIAGNOSTICO,
        EstadoServicio::PRESUPUESTADA,
        EstadoServicio::APROBADA,
        EstadoServicio::EN_REPARACION,
    ];

    public function __construct(
        private ServicioEstadoService $estados,
        private EntregaServicioService $entregas,
        private EstadoPagoServicioService $estadoPago,
        private StockServicioService $stock,
    ) {}

    /** Datos del modal: si se puede, por qué no, y la devolución si el equipo está en el taller. */
    public function configuracion(SolicitudServicio $s): array
    {
        $recibido = $s->codigoIngreso() === EstadoLogistica::RECIBIDO;
        $bloqueo  = $this->motivoBloqueo($s);

        $devolucion = null;
        if ($recibido && $bloqueo === null) {
            $devolucion = $this->entregas->configuracion($s);
            unset($devolucion['cobro']);
        }

        return [
            'estado'         => $s->codigoServicio(),
            'cancelable'     => $bloqueo === null,
            'motivo_bloqueo' => $bloqueo,
            'recibido'       => $recibido,
            'devolucion'     => $devolucion,
        ];
    }

    public function motivoBloqueo(SolicitudServicio $s): ?string
    {
        $codigo = $s->codigoServicio();

        return match (true) {
            $codigo === EstadoServicio::LISTA
                => 'El equipo ya está LISTO: se entrega (con su cobro, si corresponde); no se cancela.',
            !in_array($codigo, self::CANCELABLES, true)
                => 'La solicitud no se puede cancelar en su estado actual.',
            $s->codigoIngreso() === EstadoLogistica::EN_TRANSITO
                => 'El equipo está en tránsito: registrá su recepción antes de cancelar, para dejar constancia de la devolución.',
            $s->factura()->exists()
                => 'La solicitud tiene una factura final vigente: no se puede cancelar.',
            $s->anticipoSinResolver() > 0.01
                => 'Hay un anticipo sin resolver: rechazá el pago declarado, o acreditalo con nota de crédito y registrá la devolución del dinero.',
            default => null,
        };
    }

    /** @param array{motivo: string, devolucion?: ?array} $d */
    public function cancelar(SolicitudServicio $s, array $d, Usuario $usuario): SolicitudServicio
    {
        return DB::transaction(function () use ($s, $d, $usuario) {
            $s = SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()
                ->with(['estadoServicio', 'estadoIngreso', 'cliente.persona.contactos', 'recepcion.accesorios', 'ordenServicio'])
                ->firstOrFail();

            if ($bloqueo = $this->motivoBloqueo($s)) {
                throw ValidationException::withMessages(['estado' => $bloqueo]);
            }

            $recibido = $s->codigoIngreso() === EstadoLogistica::RECIBIDO;

            if ($recibido && empty($d['devolucion'])) {
                throw ValidationException::withMessages([
                    'devolucion' => 'El equipo está en el taller: completá los datos de su devolución.',
                ]);
            }

            $this->liberarReservas($s);

            if ($recibido) {
                $this->entregas->registrarDevolucion($s, $d['devolucion'], $usuario);
            }

            $this->estados->cambiarServicio($s, EstadoServicio::CANCELADA, $usuario->usu_id,
                'Solicitud cancelada: ' . $d['motivo'] . ($recibido ? '. Equipo devuelto sin reparar.' : ''));

            $this->estadoPago->recalcular($s, $usuario->usu_id);

            return $s->fresh(['estadoServicio', 'entrega']);
        });
    }

    /** Reservas de presupuestos aprobados (liberar() es idempotente por psd_reserv). */
    private function liberarReservas(SolicitudServicio $s): void
    {
        $orden = $s->ordenServicio;

        if (!$orden) {
            return;
        }

        PresupuestoServicio::where('pse_oseid', $orden->ose_id)->get()
            ->each(fn (PresupuestoServicio $p) => $this->stock->liberar($p));
    }
}