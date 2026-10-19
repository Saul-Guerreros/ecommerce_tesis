<?php

namespace App\Services\Servicio;

use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioHistorialEstado;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Validation\ValidationException;

/**
 * Máquina de estados de la solicitud (mismo criterio que PedidoEstadoService).
 *
 * TODO cambio de eje pasa por acá: así la regla de transiciones vive en un
 * solo lugar y TODO cambio queda en servicio_historial_estado con usuario,
 * IP y fecha (regla de negocio 9).
 *
 * Debe llamarse dentro de la DB::transaction() del Service que orquesta.
 */
class ServicioEstadoService
{
    public function __construct(private NotificadorServicio $notificador) {}

    /** Ciclo técnico. ENTREGADA y CANCELADA son terminales. */
    private const TRANSICIONES_SERVICIO = [
        EstadoServicio::SOLICITADA     => [EstadoServicio::RECIBIDA, EstadoServicio::CANCELADA],
        EstadoServicio::RECIBIDA       => [EstadoServicio::EN_DIAGNOSTICO, EstadoServicio::CANCELADA],
        EstadoServicio::EN_DIAGNOSTICO => [EstadoServicio::PRESUPUESTADA, EstadoServicio::APROBADA, EstadoServicio::LISTA],
        EstadoServicio::PRESUPUESTADA  => [EstadoServicio::APROBADA, EstadoServicio::LISTA],
        EstadoServicio::APROBADA       => [EstadoServicio::EN_REPARACION],
        EstadoServicio::EN_REPARACION  => [EstadoServicio::PRESUPUESTADA, EstadoServicio::LISTA],
        EstadoServicio::LISTA          => [EstadoServicio::ENTREGADA],
        EstadoServicio::ENTREGADA      => [],
        EstadoServicio::CANCELADA      => [],
    ];

    /**
     * Logística de ingreso. RETIRO_AGENDADO -> RETIRO_AGENDADO permite
     * reprogramar la fecha. El salto PENDIENTE -> RECIBIDO es el caso
     * "el cliente lo trae al local".
     */
    private const TRANSICIONES_INGRESO = [
        EstadoLogistica::PENDIENTE       => [EstadoLogistica::RETIRO_AGENDADO, EstadoLogistica::RECIBIDO],
        EstadoLogistica::RETIRO_AGENDADO => [EstadoLogistica::RETIRO_AGENDADO, EstadoLogistica::EN_TRANSITO],
        EstadoLogistica::EN_TRANSITO     => [EstadoLogistica::RECIBIDO],
        EstadoLogistica::RECIBIDO        => [],
    ];

    /** Logística de egreso (Paso 6). El checkout C5 (instrucción posterior) usará ESPERA_RETIRO / DESPACHADO. */
    private const TRANSICIONES_EGRESO = [
        EstadoLogistica::NO_DEFINIDO   => [EstadoLogistica::ESPERA_RETIRO, EstadoLogistica::DESPACHADO, EstadoLogistica::ENTREGADO],
        EstadoLogistica::ESPERA_RETIRO => [EstadoLogistica::DESPACHADO, EstadoLogistica::ENTREGADO],
        EstadoLogistica::DESPACHADO    => [EstadoLogistica::ENTREGADO],
        EstadoLogistica::ENTREGADO     => [],
    ];

    public function cambiarServicio(SolicitudServicio $s, string $destino, ?int $usuId, ?string $coment = null): void
    {
        $s->loadMissing('estadoServicio');
        $actual = $s->estadoServicio?->ets_codigo;

        if (!in_array($destino, self::TRANSICIONES_SERVICIO[$actual] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede pasar la solicitud de {$actual} a {$destino}.",
            ]);
        }

        $nuevo    = EstadoServicio::porCodigo($destino);
        $anterior = $s->sse_etsid;

        $s->sse_etsid = $nuevo->ets_id;
        $s->save();
        $s->setRelation('estadoServicio', $nuevo);

        $this->registrar($s, ServicioHistorialEstado::SERVICIO, $anterior, $nuevo->ets_id, $usuId, $coment);

        // RF-033: un solo punto de notificación. Se envía DESPUÉS del commit
        // para no avisar algo que la transacción termine revirtiendo.
        $this->notificador->alCambiarEstado($s, $destino, $coment);
    }

    public function cambiarIngreso(SolicitudServicio $s, string $destino, ?int $usuId, ?string $coment = null): void
    {
        $s->loadMissing('estadoIngreso');
        $actual = $s->estadoIngreso?->elg_codigo;

        if (!in_array($destino, self::TRANSICIONES_INGRESO[$actual] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede pasar el ingreso del equipo de {$actual} a {$destino}.",
            ]);
        }

        $nuevo    = EstadoLogistica::porCodigo($destino, EstadoLogistica::AMBITO_INGRESO);
        $anterior = $s->sse_elging;

        $s->sse_elging = $nuevo->elg_id;
        $s->save();
        $s->setRelation('estadoIngreso', $nuevo);

        $this->registrar($s, ServicioHistorialEstado::INGRESO, $anterior, $nuevo->elg_id, $usuId, $coment);
    }

    public function cambiarEgreso(SolicitudServicio $s, string $destino, ?int $usuId, ?string $coment = null): void
    {
        $s->loadMissing('estadoEgreso');
        $actual = $s->estadoEgreso?->elg_codigo;

        if (!in_array($destino, self::TRANSICIONES_EGRESO[$actual] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede pasar el egreso del equipo de {$actual} a {$destino}.",
            ]);
        }

        $nuevo    = EstadoLogistica::porCodigo($destino, EstadoLogistica::AMBITO_EGRESO);
        $anterior = $s->sse_elgegr;

        $s->sse_elgegr = $nuevo->elg_id;
        $s->save();
        $s->setRelation('estadoEgreso', $nuevo);

        $this->registrar($s, ServicioHistorialEstado::EGRESO, $anterior, $nuevo->elg_id, $usuId, $coment);
    }

    /** Eventos sin cambio de estado (reasignación de técnico, observaciones). */
    public function registrarNota(SolicitudServicio $s, ?int $usuId, string $coment): void
    {
        $this->registrar($s, ServicioHistorialEstado::NOTA, null, null, $usuId, $coment);
    }

    /** Registro inicial al crear la solicitud (no hay estado anterior). */
    public function registrarAlta(SolicitudServicio $s, ?int $usuId): void
    {
        $this->registrar($s, ServicioHistorialEstado::SERVICIO, null, $s->sse_etsid, $usuId, 'Solicitud registrada por el cliente');
        $this->registrar($s, ServicioHistorialEstado::INGRESO, null, $s->sse_elging, $usuId, null);
    }

    private function registrar(SolicitudServicio $s, string $tipo, ?int $ant, ?int $nuevo, ?int $usuId, ?string $coment): void
    {
        ServicioHistorialEstado::create([
            'she_sseid'  => $s->sse_id,
            'she_usuid'  => $usuId,
            'she_tipo'   => $tipo,
            'she_estant' => $ant,
            'she_estid'  => $nuevo,
            'she_coment' => $coment,
            'she_ip'     => request()->ip(),
        ]);
    }
}
