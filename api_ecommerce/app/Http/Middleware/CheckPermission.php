<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware de autorizacion basado en el modulo de Seguridad y Acceso del DER
 * (rol -> det_rol_permiso -> permiso).
 *
 * Uso en rutas:
 *   Route::get('/algo', [Controller::class, 'metodo'])->middleware('permiso:productos.ver');
 *
 * Se puede exigir mas de un permiso (el usuario debe tener AL MENOS UNO de ellos):
 *   ->middleware('permiso:productos.ver,productos.editar')
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permisosRequeridos)
    {
        $usuario = auth('api')->user();

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // El Administrador tiene acceso a todo, sin necesidad de listar cada permiso.
        if ($usuario->rol?->rol_descri === 'Administrador') {
            return $next($request);
        }

        if (empty($permisosRequeridos)) {
            return $next($request);
        }

        foreach ($permisosRequeridos as $permiso) {
            if ($usuario->tienePermiso($permiso)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'No tiene permisos para realizar esta accion',
        ], 403);
    }
}
