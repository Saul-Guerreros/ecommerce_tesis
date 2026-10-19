<?php

namespace App\Services\Servicio;

use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * T1 - Órdenes del técnico. El técnico ve SOLO lo que tiene asignado;
 * el Administrador ve todo (y puede filtrar por técnico).
 */
class OrdenTecnicoConsultaService
{
    /** Estados que se muestran en el tablero (lo entregado/cancelado ya no es trabajo). */
    public const ESTADOS_TABLERO = [
        EstadoServicio::RECIBIDA, EstadoServicio::EN_DIAGNOSTICO, EstadoServicio::PRESUPUESTADA,
        EstadoServicio::APROBADA, EstadoServicio::EN_REPARACION, EstadoServicio::LISTA,
    ];

    public static function esAdmin(Usuario $u): bool
    {
        return $u->rol?->rol_descri === 'Administrador';
    }

    public function tablero(Usuario $usuario, ?int $tecnicoId = null, ?string $buscar = null): Collection
    {
        return SolicitudServicio::query()
            ->whereHas('estadoServicio', fn ($q) => $q->whereIn('ets_codigo', self::ESTADOS_TABLERO))
            ->whereHas('recepcion', function ($q) use ($usuario, $tecnicoId) {
                if (!self::esAdmin($usuario)) {
                    $q->where('rse_tecid', $usuario->usu_id);
                } elseif ($tecnicoId) {
                    $q->where('rse_tecid', $tecnicoId);
                }
            })
            ->when($buscar, function ($q, $b) {
                $like = '%' . mb_strtolower($b) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(sse_numero) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sse_marca) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sse_modelo) LIKE ?', [$like])
                    ->orWhereHas('ordenServicio', fn ($o) => $o->whereRaw('LOWER(ose_numero) LIKE ?', [$like])));
            })
            ->with([
                'tipoDispositivo', 'estadoServicio', 'ordenServicio:ose_id,ose_sseid,ose_numero,ose_fecini',
                'recepcion:rse_id,rse_sseid,rse_tecid,rse_fecha', 'recepcion.tecnico.persona:per_id,per_nombr1,per_apell1',
            ])
            // Orden de llegada (decisión de alcance: sin prioridad automática).
            ->orderBy('sse_id')
            ->get();
    }

    public function puedeVer(SolicitudServicio $s, Usuario $u): bool
    {
        return self::esAdmin($u) || (int) $s->recepcion?->rse_tecid === (int) $u->usu_id;
    }
}
