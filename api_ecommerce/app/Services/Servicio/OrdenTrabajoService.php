<?php

namespace App\Services\Servicio;

use App\Models\Servicio\Diagnostico;
use App\Models\Servicio\DiagnosticoFalla;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\OrdenServicio;
use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\Trabajo;
use App\Models\Usuario;
use App\Services\Servicio\ServicioTotalesService as Totales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FASE 2 - Orden de trabajo y diagnóstico (T1 / T2).
 */
class OrdenTrabajoService
{
    public function __construct(
        private ServicioEstadoService $estados,
        private ServicioTotalesService $totales,
    ) {}

    /** Crea la OT y pasa la solicitud a EN_DIAGNOSTICO. */
    public function iniciar(SolicitudServicio $s, Usuario $usuario): OrdenServicio
    {
        if ($s->codigoServicio() !== EstadoServicio::RECIBIDA) {
            throw ValidationException::withMessages(['orden' => 'Solo se puede iniciar una orden para un equipo recibido.']);
        }
        if ($s->ordenServicio()->exists()) {
            throw ValidationException::withMessages(['orden' => 'Esta solicitud ya tiene una orden de trabajo.']);
        }
        // La garantía de compra la cubre la empresa responsable: no se diagnostica ni se presupuesta.
        if ($s->esGarantiaDeCompra()) {
            throw ValidationException::withMessages([
                'orden' => 'Las garantías de compra se gestionan con la empresa responsable: use «Iniciar gestión de garantía».',
            ]);
        }

        return DB::transaction(function () use ($s, $usuario) {
            $orden = OrdenServicio::create([
                'ose_sseid'  => $s->sse_id,
                // La OT queda a nombre del técnico asignado en la recepción,
                // aunque la inicie un administrador.
                'ose_usuid'  => $s->recepcion->rse_tecid,
                'ose_fecini' => now(),
            ]);

            $orden->update([
                'ose_numero' => 'OT-' . date('Y') . '-' . str_pad((string) $orden->ose_id, 5, '0', STR_PAD_LEFT),
            ]);

            $this->estados->cambiarServicio($s, EstadoServicio::EN_DIAGNOSTICO, $usuario->usu_id, "Orden {$orden->ose_numero} iniciada");

            return $orden;
        });
    }

    /**
     * Registra o corrige el diagnóstico mientras la solicitud sigue EN_DIAGNOSTICO
     * (una vez presupuestado ya no se toca: el presupuesto se basa en él).
     */
    public function guardarDiagnostico(OrdenServicio $orden, array $d, array $fotos, Usuario $usuario): Diagnostico
    {
        $s = $orden->solicitud;
        $this->exigirEnDiagnostico($s, $orden);

        $rutas = [];

        try {
            return DB::transaction(function () use ($orden, $s, $d, $fotos, $usuario, &$rutas) {
                $existia = $orden->diagnostico()->exists();

                $diagnostico = Diagnostico::updateOrCreate(
                    ['dia_oseid' => $orden->ose_id],
                    [
                        'dia_usuid'  => $usuario->usu_id,
                        'dia_causa'  => $d['dia_causa'],
                        'dia_repara' => $d['dia_repara'],
                        'dia_reqrep' => $d['dia_repara'] ? $d['dia_reqrep'] : false,
                        'dia_tiempo' => $d['dia_repara'] ? $d['dia_tiempo'] : null,
                        'dia_fecha'  => now(),
                    ]
                );

                // Las fallas se reemplazan completas: es más simple y seguro que
                // calcular altas/bajas, y el diagnóstico todavía es un borrador.
                $diagnostico->fallas()->delete();
                foreach ($d['fallas'] as $f) {
                    DiagnosticoFalla::create([
                        'dfa_diaid' => $diagnostico->dia_id,
                        'dfa_fllid' => $f['fll_id'],
                        'dfa_obs'   => $f['obs'] ?? null,
                    ]);
                }

                $rutas = AdjuntoServicio::guardar($s, $fotos, SolicitudAdjunto::DIAGNOSTICO, $usuario->usu_id);

                $this->estados->registrarNota(
                    $s, $usuario->usu_id,
                    ($existia ? 'Diagnóstico actualizado' : 'Diagnóstico registrado')
                        . ($d['dia_repara'] ? '' : ' (equipo no reparable)')
                );

                return $diagnostico->fresh('fallas.falla');
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }
    }

    /**
     * Equipo NO reparable: cierra la OT y deja la solicitud LISTA para
     * devolver (salta a la Fase 6). Opcionalmente cobra la revisión.
     */
    public function cerrarNoReparable(OrdenServicio $orden, bool $cobrarRevision, ?string $proced, Usuario $usuario): OrdenServicio
    {
        $s = $orden->solicitud;
        $this->exigirEnDiagnostico($s, $orden);

        $diagnostico = $orden->diagnostico;
        if (!$diagnostico || $diagnostico->dia_repara) {
            throw ValidationException::withMessages([
                'cobrar_revision' => 'Primero registre un diagnóstico que indique que el equipo no es reparable.',
            ]);
        }

        // En garantía nunca se cobra (regla 8).
        $cobrarRevision = $cobrarRevision && !$s->sse_garant;
        $revision = $cobrarRevision ? $this->trabajoRevision() : null;

        return DB::transaction(function () use ($orden, $s, $diagnostico, $revision, $proced, $usuario) {
            $orden->update([
                'ose_result' => OrdenServicio::NO_REPARABLE,
                'ose_fecfin' => now(),
                'ose_proced' => $proced ?: $diagnostico->dia_causa,
                'ose_gardia' => 0,
            ]);

            $subtot = $revision ? (float) $revision->trb_precio : 0.0;
            $impues = $revision ? Totales::ivaIncluido($subtot, (float) $revision->tipoIva->tiv_tasa) : 0.0;

            $this->totales->consolidar($s, $subtot, $impues, $usuario->usu_id);

            $coment = 'Equipo no reparable. '
                . ($revision
                    ? 'Se cobra la revisión: Gs. ' . number_format($subtot, 0, ',', '.')
                    : 'Sin cargo de revisión.');

            $this->estados->cambiarServicio($s, EstadoServicio::LISTA, $usuario->usu_id, $coment);

            return $orden->fresh();
        });
    }

    /** Devuelve la clave del equipo y deja constancia de quién la consultó. */
    public function revelarClave(SolicitudServicio $s, Usuario $usuario): ?string
    {
        $clave = $s->recepcion?->rse_clave;

        if ($clave !== null) {
            $this->estados->registrarNota($s, $usuario->usu_id, 'Clave del equipo consultada');
        }

        return $clave;
    }

    // ---------- Auxiliares ----------

    private function exigirEnDiagnostico(SolicitudServicio $s, OrdenServicio $orden): void
    {
        if ($s->codigoServicio() !== EstadoServicio::EN_DIAGNOSTICO || $orden->estaCerrada()) {
            throw ValidationException::withMessages([
                'dia_causa' => 'El diagnóstico solo se puede modificar mientras la orden está en diagnóstico.',
            ]);
        }

        // Paso 4: una vez que el presupuesto sale del borrador, el diagnóstico
        // en el que se basa queda congelado.
        $presupuesto = $orden->presupuestoActual;
        if ($presupuesto && !$presupuesto->esEditable()) {
            throw ValidationException::withMessages([
                'dia_causa' => 'El presupuesto ya fue enviado: el diagnóstico no puede modificarse.',
            ]);
        }
    }

    private function trabajoRevision(): Trabajo
    {
        $codigo  = config('servicio.trabajo_revision');
        $trabajo = Trabajo::activos()->with('tipoIva')->where('trb_codigo', $codigo)->first();

        if (!$trabajo) {
            throw ValidationException::withMessages([
                'cobrar_revision' => "No existe el trabajo activo «{$codigo}» en el catálogo de trabajos.",
            ]);
        }

        return $trabajo;
    }
}
