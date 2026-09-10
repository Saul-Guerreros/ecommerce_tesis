<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RolRequest;
use App\Http\Resources\Auth\RolResource;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    /**
     * Roles sembrados por RolPermisoSeeder de los que depende logica de
     * negocio real (AuthController, UsuarioController filtran por estos
     * nombres exactos). No se pueden renombrar ni eliminar desde el CRUD,
     * aunque su lista de permisos si se puede editar libremente.
     */
    private const ROLES_PROTEGIDOS = ['Administrador', 'Vendedor', 'Cliente'];

    // GET /api/admin/roles?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Rol::withCount('usuarios');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(rol_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('rol_estado', $request->estado);
        }

        $roles = $query->orderBy('rol_descri')->get();

        return response()->json(['data' => RolResource::collection($roles)], 200);
    }

    /**
     * GET /api/admin/roles/config
     * Catalogo completo de permisos activos, para armar el checklist.
     */
    public function config(): JsonResponse
    {
        $permisos = Permiso::where('prm_estado', 1)
            ->orderBy('prm_descri')
            ->get(['prm_id', 'prm_descri']);

        return response()->json(['permisos' => $permisos], 200);
    }

    // GET /api/admin/roles/{id}
    public function show(int $id): JsonResponse
    {
        $rol = Rol::with('permisos')->withCount('usuarios')->find($id);

        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        return response()->json(['data' => new RolResource($rol)], 200);
    }

    // POST /api/admin/roles
    public function store(RolRequest $request): JsonResponse
    {
        $rol = DB::transaction(function () use ($request) {
            $rol = Rol::create([
                'rol_descri' => $request->rol_descri,
                'rol_estado' => $request->rol_estado ?? 1,
            ]);

            $rol->permisos()->sync($request->input('permisos', []));

            return $rol;
        });

        $rol->load('permisos');

        return response()->json([
            'message' => 'Rol registrado correctamente',
            'data'    => new RolResource($rol),
        ], 201);
    }

    // PUT /api/admin/roles/{id}
    public function update(RolRequest $request, int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        if (in_array($rol->rol_descri, self::ROLES_PROTEGIDOS, true) && $request->rol_descri !== $rol->rol_descri) {
            return response()->json([
                'message' => "El rol \"{$rol->rol_descri}\" es utilizado internamente por el sistema y no se puede renombrar. Sí puede editar sus permisos.",
            ], 409);
        }

        DB::transaction(function () use ($request, $rol) {
            $rol->update([
                'rol_descri' => $request->rol_descri,
                'rol_estado' => $request->rol_estado ?? $rol->rol_estado,
            ]);

            $rol->permisos()->sync($request->input('permisos', []));
        });

        $rol->load('permisos');

        return response()->json([
            'message' => 'Rol actualizado correctamente',
            'data'    => new RolResource($rol->fresh('permisos')),
        ], 200);
    }

    // DELETE /api/admin/roles/{id}
    public function destroy(int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        if (in_array($rol->rol_descri, self::ROLES_PROTEGIDOS, true)) {
            return response()->json([
                'message' => "El rol \"{$rol->rol_descri}\" es utilizado internamente por el sistema y no se puede eliminar.",
            ], 409);
        }

        if ($rol->usuarios()->exists()) {
            $total = $rol->usuarios()->count();
            return response()->json([
                'message' => "No se puede eliminar: hay {$total} usuario(s) con este rol asignado. Desactívelo en lugar de eliminarlo.",
            ], 409);
        }

        DB::transaction(function () use ($rol) {
            $rol->permisos()->detach();
            $rol->delete();
        });

        return response()->json(['message' => 'Rol eliminado correctamente'], 200);
    }

    // PATCH /api/admin/roles/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        if (in_array($rol->rol_descri, self::ROLES_PROTEGIDOS, true)) {
            return response()->json([
                'message' => "El rol \"{$rol->rol_descri}\" es utilizado internamente por el sistema y no se puede desactivar.",
            ], 409);
        }

        $rol->rol_estado = $rol->rol_estado == 1 ? 0 : 1;
        $rol->save();

        return response()->json([
            'message' => $rol->rol_estado == 1 ? 'Rol activado correctamente' : 'Rol desactivado correctamente',
            'data'    => new RolResource($rol),
        ], 200);
    }
}