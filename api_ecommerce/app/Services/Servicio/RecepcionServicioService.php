<?php

namespace App\Services\Servicio;

use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\RecepcionAccesorio;
use App\Models\Servicio\RecepcionServicio;
use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FASE 1 - Recepción (A1 / A2). Toda operación es transaccional porque
 * toca la cabecera + historial (y en la recepción, 3 tablas más).
 */
class RecepcionServicioService
{
    public function __construct(
        private ServicioEstadoService $estados,
        private TecnicoService $tecnicos,
    ) {}

    /** Retiro a domicilio: agenda (o reprograma) la fecha de retiro. */
    public function agendarRetiro(SolicitudServicio $s, string $fecha, ?string $coment, Usuario $usuario): SolicitudServicio
    {
        $this->exigirADomicilio($s);

        return DB::transaction(function () use ($s, $fecha, $coment, $usuario) {
            $s->update(['sse_fecing' => $fecha]);

            $texto = 'Retiro agendado para el ' . date('d/m/Y', strtotime($fecha))
                   . ($coment ? ". {$coment}" : '');

            $this->estados->cambiarIngreso($s, EstadoLogistica::RETIRO_AGENDADO, $usuario->usu_id, $texto);

            return $s->fresh();
        });
    }

    public function marcarEnTransito(SolicitudServicio $s, Usuario $usuario): SolicitudServicio
    {
        $this->exigirADomicilio($s);

        return DB::transaction(function () use ($s, $usuario) {
            $this->estados->cambiarIngreso($s, EstadoLogistica::EN_TRANSITO, $usuario->usu_id, 'Equipo retirado del domicilio');

            return $s->fresh();
        });
    }

    /** Rechazo: solo antes de que el equipo esté en el taller. */
    public function rechazar(SolicitudServicio $s, string $motivo, Usuario $usuario): SolicitudServicio
    {
        if ($s->codigoIngreso() === EstadoLogistica::RECIBIDO) {
            throw ValidationException::withMessages([
                'motivo' => 'El equipo ya fue recibido: no se puede rechazar la solicitud.',
            ]);
        }

        return DB::transaction(function () use ($s, $motivo, $usuario) {
            $this->estados->cambiarServicio($s, EstadoServicio::CANCELADA, $usuario->usu_id, "Solicitud rechazada: {$motivo}");

            return $s->fresh();
        });
    }

    /**
     * Confirma la recepción física (A2): recepcion_servicio + accesorios +
     * fotos de INGRESO + técnico asignado, y mueve los ejes a RECIBIDA/RECIBIDO.
     */
    public function confirmarRecepcion(SolicitudServicio $s, array $datos, array $fotos, Usuario $usuario): SolicitudServicio
    {
        if ($s->recepcion()->exists()) {
            throw ValidationException::withMessages(['rse_tecid' => 'La recepción de este equipo ya fue registrada.']);
        }

        // Cada sucursal tiene su taller: el técnico debe estar asignado a la sucursal de la solicitud.
        if (!$this->tecnicos->esTecnicoActivo((int) $datos['rse_tecid'], $s->sse_sucid)) {
            throw ValidationException::withMessages([
                'rse_tecid' => 'El técnico seleccionado no está asignado a la sucursal de esta solicitud.',
            ]);
        }

        // A domicilio: el equipo tiene que venir en camino (o al menos agendado).
        if ($s->esADomicilio() && $s->codigoIngreso() === EstadoLogistica::PENDIENTE) {
            throw ValidationException::withMessages(['rse_tecid' => 'Primero agende el retiro del equipo.']);
        }

        $rutas = [];

        try {
            return DB::transaction(function () use ($s, $datos, $fotos, $usuario, &$rutas) {
                $recepcion = RecepcionServicio::create([
                    'rse_sseid'  => $s->sse_id,
                    'rse_usuid'  => $usuario->usu_id,
                    'rse_tecid'  => $datos['rse_tecid'],
                    'rse_fecha'  => now(),
                    'rse_estfis' => $datos['rse_estfis'],
                    'rse_clave'  => $datos['rse_clave'] ?? null,
                    'rse_obs'    => $datos['rse_obs'] ?? null,
                ]);

                foreach ($datos['accesorios'] ?? [] as $descri) {
                    RecepcionAccesorio::create(['rac_rseid' => $recepcion->rse_id, 'rac_descri' => $descri]);
                }

                $rutas = AdjuntoServicio::guardar($s, $fotos, SolicitudAdjunto::INGRESO, $usuario->usu_id);

                // Si viene en tránsito o agendado, el ingreso cierra en RECIBIDO.
                // Si el cliente lo trajo, PENDIENTE -> RECIBIDO directo.
                if ($s->codigoIngreso() === EstadoLogistica::RETIRO_AGENDADO) {
                    $this->estados->cambiarIngreso($s, EstadoLogistica::EN_TRANSITO, $usuario->usu_id, 'Equipo retirado del domicilio');
                }
                $this->estados->cambiarIngreso($s, EstadoLogistica::RECIBIDO, $usuario->usu_id, null);

                $tecnico = Usuario::with('persona')->find($datos['rse_tecid']);
                $this->estados->cambiarServicio(
                    $s, EstadoServicio::RECIBIDA, $usuario->usu_id,
                    'Equipo recibido. Técnico asignado: ' . ($tecnico?->persona?->nombre_completo ?? $tecnico?->usu_login)
                );

                return $s->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }
    }

    private function exigirADomicilio(SolicitudServicio $s): void
    {
        if (!$s->esADomicilio()) {
            throw ValidationException::withMessages([
                'sse_fecing' => 'Esta solicitud no es con retiro a domicilio: el cliente trae el equipo al local.',
            ]);
        }
    }
}
