<?php

namespace App\Http\Resources\Seguridad;

use Carbon\Carbon;

/**
 * Helpers compartidos por los resources de auditoría.
 * Las fechas salen en ISO 8601 con el offset de la zona horaria de la app,
 * para que el frontend las muestre bien aunque la columna no tenga cast.
 */
trait Concerns
{
    protected function iso($valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toIso8601String() : null;
    }

    /** Usuario asociado (login + nombre). Nunca incluye la contraseña. */
    protected function datosUsuario($usuario): ?array
    {
        if (!$usuario) {
            return null;
        }

        return [
            'usu_id'    => $usuario->usu_id,
            'usu_login' => $usuario->usu_login,
            'nombre'    => $usuario->persona?->nombre_completo,
        ];
    }

    protected function ucfirst(?string $texto): ?string
    {
        if ($texto === null || $texto === '') {
            return $texto;
        }

        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }
}
