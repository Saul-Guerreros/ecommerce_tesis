<?php

namespace App\Console\Commands;

use App\Jobs\ClasificarSolicitudServicio;
use App\Models\Servicio\ClasificacionSolicitud;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\TipoDispositivo;
use Illuminate\Console\Command;

/**
 * Punto 7 - Reintenta las clasificaciones pendientes o no disponibles.
 *   php artisan servicio:clasificar-pendientes             (programado cada 15 min)
 *   php artisan servicio:clasificar-pendientes --historico (solicitudes previas a la funcionalidad)
 */
class ClasificarSolicitudesPendientes extends Command
{
    protected $signature = 'servicio:clasificar-pendientes {--historico : Crea y encola la clasificación de solicitudes que no la tienen}';

    protected $description = 'Encola la clasificación IA de solicitudes PENDIENTES o NO_DISPONIBLES';

    private const MAX_INTENTOS = 5;

    public function handle(): int
    {
        if ($this->option('historico')) {
            $pendiente = TipoDispositivo::pendienteId();

            SolicitudServicio::doesntHave('clasificacion')
                ->orderBy('sse_id')
                ->each(function (SolicitudServicio $s) use ($pendiente) {
                    $tdi = (int) $s->sse_tdiid === $pendiente ? null : (int) $s->sse_tdiid;
                   ClasificacionSolicitud::create([
                        'cls_sseid'  => $s->sse_id,
                        'cls_estado' => ClasificacionSolicitud::PENDIENTE,
                        'cls_tdicli' => $tdi,
                        'cls_tdiid'  => $tdi,
                    ]);
                    ClasificarSolicitudServicio::dispatch($s->sse_id);  
                });
        }

        $ids = ClasificacionSolicitud::query()
            ->where(fn ($q) => $q
                // PENDIENTE con más de 5 minutos: el job se perdió o el worker estuvo caído.
                ->where(fn ($p) => $p->where('cls_estado', ClasificacionSolicitud::PENDIENTE)
                    ->where('cls_creado', '<', now()->subMinutes(5)))
                ->orWhere(fn ($n) => $n->where('cls_estado', ClasificacionSolicitud::NO_DISPONIBLE)
                    ->where('cls_intent', '<', self::MAX_INTENTOS)))
            ->orderBy('cls_sseid')
            ->limit(200)
            ->pluck('cls_sseid');

        $ids->each(fn ($id) => ClasificarSolicitudServicio::dispatch((int) $id));

        $this->info("Clasificaciones encoladas: {$ids->count()}");

        return self::SUCCESS;
    }
}