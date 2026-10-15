<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GuardarPermisosRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\OpcionesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Matriz de acceso: qué opciones del panel admin puede usar cada rol.
 * Trabaja sobre las mismas tablas que la pantalla Roles (det_rol_permiso),
 * pero SOLO sobre los permisos del catálogo OpcionesAdmin: los permisos finos
 * (pedidos.exportar, usuarios.gestionar, ...) no se tocan desde aquí.
 */
class PermisoController extends Controller
{
    /** Roles cuyos permisos no se editan desde la matriz. */
    private const ROLES_NO_EDITABLES = ['Administrador', 'Cliente'];

    // GET /api/admin/permisos/matriz
    public function matriz(): JsonResponse
    {
        $codigos = OpcionesAdmin::codigos();

        // Cliente no entra al panel: no tiene sentido como columna.
        $roles = Rol::where('rol_estado', 1)
            ->where('rol_descri', '!=', 'Cliente')
            ->with('permisos:prm_id,prm_descri')
            ->orderByRaw("CASE WHEN rol_descri = 'Administrador' THEN 0 ELSE 1 END")
            ->orderBy('rol_descri')
            ->get();

        $asignados = [];
        foreach ($roles as $rol) {
            // El Administrador tiene acceso total (el middleware lo deja pasar siempre).
            $asignados[$rol->rol_id] = $rol->rol_descri === 'Administrador'
                ? $codigos
                : $rol->permisos->pluck('prm_descri')->intersect($codigos)->values()->all();
        }

        return response()->json([
            'roles' => $roles->map(fn ($r) => [
                'rol_id'     => $r->rol_id,
                'rol_descri' => $r->rol_descri,
                'bloqueado'  => $r->rol_descri === 'Administrador',
            ])->values(),
            'grupos'    => OpcionesAdmin::grupos(),
            'asignados' => (object) $asignados,
        ], 200);
    }

    // PUT /api/admin/permisos/matriz   { cambios: [{ rol_id, permisos: ['codigo', ...] }] }
    public function guardar(GuardarPermisosRequest $request): JsonResponse
    {
        $editables = OpcionesAdmin::codigosEditables();
        $usuario   = auth('api')->user();

        $roles = Rol::whereIn('rol_id', collect($request->cambios)->pluck('rol_id'))
            ->get()
            ->keyBy('rol_id');

        foreach ($request->cambios as $cambio) {
            $rol = $roles[$cambio['rol_id']];

            if (in_array($rol->rol_descri, self::ROLES_NO_EDITABLES, true)) {
                return response()->json([
                    'message' => "Los permisos del rol \"{$rol->rol_descri}\" no se pueden modificar desde esta pantalla.",
                ], 422);
            }

            // Evita que alguien se quite a sí mismo el acceso al panel o a esta pantalla.
            if ((int) $usuario->usu_rolid === (int) $rol->rol_id) {
                foreach (['dashboard.ver', 'permisos.gestionar'] as $imprescindible) {
                    if (!in_array($imprescindible, $cambio['permisos'], true)) {
                        return response()->json([
                            'message' => 'No puede quitarle a su propio rol el acceso al panel ni a la pantalla Permisos.',
                        ], 422);
                    }
                }
            }
        }

        DB::transaction(function () use ($request, $roles, $editables) {
            // Garantiza que existan las filas de permiso del catálogo.
            foreach ($editables as $codigo) {
                Permiso::firstOrCreate(['prm_descri' => $codigo], ['prm_estado' => 1]);
            }

            $ids = Permiso::whereIn('prm_descri', $editables)->pluck('prm_id', 'prm_descri');

            foreach ($request->cambios as $cambio) {
                $rol       = $roles[$cambio['rol_id']];
                $seleccion = array_values(array_intersect($cambio['permisos'], $editables));

                $quitar  = $ids->except($seleccion)->values()->all();
                $agregar = $ids->only($seleccion)->values()->all();

                if ($quitar)  { $rol->permisos()->detach($quitar); }
                if ($agregar) { $rol->permisos()->syncWithoutDetaching($agregar); }
            }
        });

        return response()->json([
            'message'            => 'Permisos actualizados correctamente',
            'roles_actualizados' => count($request->cambios),
        ], 200);
    }
}
