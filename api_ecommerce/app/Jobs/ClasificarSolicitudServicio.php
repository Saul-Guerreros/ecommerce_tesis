<?php

namespace App\Jobs;

use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\ClasificacionSolicitudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Punto 7 - Clasifica una solicitud fuera de la petición que la creó.
 * Si la IA no responde, el servicio deja NO_DISPONIBLE (no lanza excepción);
 * el comando servicio:clasificar-pendientes la vuelve a intentar.
 */
class ClasificarSolicitudServicio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Mayor que el tiempo total de Gemini (diagnostico_tiempo_total). */
    public int $timeout = 90;

    public function __construct(public int $solicitudId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ClasificacionSolicitudService $servicio): void
    {
        $solicitud = SolicitudServicio::find($this->solicitudId);

        if ($solicitud) {
            $servicio->clasificar($solicitud);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning("Clasificación IA: falló la solicitud {$this->solicitudId}", ['error' => $e->getMessage()]);

        // Que no quede "Clasificando..." para siempre: pasa a NO_DISPONIBLE y el comando la reintenta.
        \App\Models\Servicio\ClasificacionSolicitud::where('cls_sseid', $this->solicitudId)
            ->where('cls_estado', \App\Models\Servicio\ClasificacionSolicitud::PENDIENTE)
            ->increment('cls_intent', 1, ['cls_estado' => \App\Models\Servicio\ClasificacionSolicitud::NO_DISPONIBLE]);
    }
}