<?php

namespace App\Services\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Técnicos = usuarios ACTIVOS con rol "Técnico". Lo usa el combo de A2,
 * el wizard del mostrador y la reasignación del panel general.
 *
 * Cada sucursal tiene su propio taller: un técnico atiende las sucursales que tiene
 * asignadas y activas en usuario_sucursal. Un técnico sin sucursal asignada no aparece
 * en ninguna (decisión B.7 / pregunta 3-a): hay que asignársela desde Usuarios.
 *
 * Cada fila incluye 'sucursales' (ids) para que el panel filtre el combo según la
 * sucursal de la solicitud sin pedir otra vez la lista.
 */
class TecnicoService
{
    public const ROL = 'Técnico';

    /** @param int|null $sucId null = todos los técnicos activos (filtros y carga del panel). */
    public function activos(?int $sucId = null): Collection
    {
        return $this->base($sucId)
            ->with([
                'persona:per_id,per_nombr1,per_nombr2,per_apell1,per_apell2',
                'usuarioSucursales' => fn ($q) => $q->where('uss_estado', 1)->select('uss_id', 'uss_usuid', 'uss_sucid'),
            ])
            ->get(['usu_id', 'usu_login', 'usu_perid'])
            ->map(fn ($u) => [
                'usu_id'     => $u->usu_id,
                'nombre'     => $u->persona?->nombre_completo ?: $u->usu_login,
                'sucursales' => $u->usuarioSucursales->pluck('uss_sucid')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->sortBy('nombre')
            ->values();
    }

    /**
     * @param int|null $sucId sucursal de la solicitud. Con sucursal, el técnico debe estar
     *                        asignado a ella. Sin sucursal (retiro a domicilio sin tarifa), basta con ser técnico activo.
     */
    public function esTecnicoActivo(int $usuId, ?int $sucId = null): bool
    {
        return $this->base($sucId)->where('usu_id', $usuId)->exists();
    }

    private function base(?int $sucId): Builder
    {
        return Usuario::query()
            ->where('usu_estado', Usuario::ESTADO_ACTIVO)
            ->whereHas('rol', fn ($q) => $q->where('rol_descri', self::ROL))
            ->when($sucId, fn (Builder $q) => $q->whereHas('usuarioSucursales', fn ($u) => $u
                ->where('uss_sucid', $sucId)
                ->where('uss_estado', 1)));
    }
}
