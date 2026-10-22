<?php

namespace App\Services\Servicio;

use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-040 - Reasignación de técnico desde el panel general (A3).
 * Mueve la asignación (rse_tecid) y, si la OT sigue abierta, también el
 * responsable de la OT (ose_usuid). Queda en el historial con el motivo.
 */
class ReasignacionService
{
    private const ESTADOS_REASIGNABLES = [
        EstadoServicio::RECIBIDA, EstadoServicio::EN_DIAGNOSTICO, EstadoServicio::PRESUPUESTADA,
        EstadoServicio::APROBADA, EstadoServicio::EN_REPARACION,
        EstadoServicio::GESTION_GARANTIA, EstadoServicio::EN_EMPRESA,
    ];

    public function __construct(
        private ServicioEstadoService $estados,
        private TecnicoService $tecnicos,
    ) {}

    public function reasignar(SolicitudServicio $s, int $tecnicoId, string $motivo, Usuario $admin): void
    {
        $recepcion = $s->recepcion;

        if (!$recepcion || !in_array($s->codigoServicio(), self::ESTADOS_REASIGNABLES, true)) {
            throw ValidationException::withMessages(['rse_tecid' => 'Esta solicitud no admite reasignación en su estado actual.']);
        }
        if (!$this->tecnicos->esTecnicoActivo($tecnicoId)) {
            throw ValidationException::withMessages(['rse_tecid' => 'El usuario seleccionado no es un técnico activo.']);
        }
        if ((int) $recepcion->rse_tecid === $tecnicoId) {
            throw ValidationException::withMessages(['rse_tecid' => 'La orden ya está asignada a ese técnico.']);
        }

        DB::transaction(function () use ($s, $recepcion, $tecnicoId, $motivo, $admin) {
            $anterior = Usuario::with('persona')->find($recepcion->rse_tecid);
            $nuevo    = Usuario::with('persona')->find($tecnicoId);

            $recepcion->update(['rse_tecid' => $tecnicoId]);

            $orden = $s->ordenServicio;
            if ($orden && !$orden->estaCerrada()) {
                $orden->update(['ose_usuid' => $tecnicoId]);
            }

            $this->estados->registrarNota($s, $admin->usu_id, sprintf(
                'Técnico reasignado: %s → %s. Motivo: %s',
                $anterior?->persona?->nombre_completo ?? '—',
                $nuevo?->persona?->nombre_completo ?? $nuevo?->usu_login,
                $motivo,
            ));
        });
    }
}
