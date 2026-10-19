<?php

namespace App\Services\Servicio;

use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Técnicos = usuarios ACTIVOS con rol "Técnico". Lo usa el combo de A2
 * y (en el Paso 6) la reasignación del panel general.
 */
class TecnicoService
{
    public const ROL = 'Técnico';

    public function activos(): Collection
    {
        return Usuario::where('usu_estado', Usuario::ESTADO_ACTIVO)
            ->whereHas('rol', fn ($q) => $q->where('rol_descri', self::ROL))
            ->with('persona:per_id,per_nombr1,per_nombr2,per_apell1,per_apell2')
            ->get(['usu_id', 'usu_login', 'usu_perid'])
            ->map(fn ($u) => [
                'usu_id' => $u->usu_id,
                'nombre' => $u->persona?->nombre_completo ?: $u->usu_login,
            ])
            ->sortBy('nombre')
            ->values();
    }

    public function esTecnicoActivo(int $usuId): bool
    {
        return Usuario::where('usu_id', $usuId)
            ->where('usu_estado', Usuario::ESTADO_ACTIVO)
            ->whereHas('rol', fn ($q) => $q->where('rol_descri', self::ROL))
            ->exists();
    }
}
