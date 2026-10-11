<?php

namespace App\Http\Controllers\Admin\Perfil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\CambiarPasswordRequest;
use App\Http\Requests\Perfil\PerfilRequest;
use App\Http\Resources\Perfil\PerfilResource;
use App\Models\HistorialPassword;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Mi perfil" del panel administrativo: cada usuario del personal gestiona
 * sus propios datos personales, foto y contraseña. El email (login), el rol
 * y los permisos son de solo lectura: los administra el módulo de Usuarios.
 */
class PerfilController extends Controller
{
    private const RELACIONES = ['persona.contactos', 'persona.tipoDocumento', 'rol.permisos'];

    // GET /api/admin/perfil
    public function show(): JsonResponse
    {
        $usuario = $this->usuarioPanel()->load(self::RELACIONES);

        return response()->json(['data' => new PerfilResource($usuario)], 200);
    }

    // POST /api/admin/perfil  (FormData: puede incluir avatar)
    public function update(PerfilRequest $request): JsonResponse
    {
        $usuario = $this->usuarioPanel();
        $persona = $usuario->persona;

        if (!$persona) {
            return response()->json(['message' => 'El usuario no tiene datos personales asociados.'], 409);
        }

        $data = $request->validated();
        $avatarAnterior = $persona->per_avatar;
        $avatarNuevo = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        try {
            // persona + contacto: más de una tabla → transacción
            DB::transaction(function () use ($persona, $data, $avatarNuevo) {
                $persona->update([
                    'per_nombr1' => $data['per_nombr1'],
                    'per_nombr2' => $data['per_nombr2'] ?? null,
                    'per_apell1' => $data['per_apell1'],
                    'per_apell2' => $data['per_apell2'] ?? null,
                    'per_fechna' => $data['per_fechna'] ?? null,
                ] + ($avatarNuevo ? ['per_avatar' => $avatarNuevo] : []));

                if (!empty($data['celular'])) {
                    $persona->contactos()->updateOrCreate(
                        ['con_tipo' => 'CELULAR'],
                        ['con_valor' => $data['celular'], 'con_estado' => 1]
                    );
                }
            });
        } catch (Throwable $e) {
            // Si falla la BD, no dejar la imagen nueva huérfana
            if ($avatarNuevo) {
                Storage::disk('public')->delete($avatarNuevo);
            }
            throw $e;
        }

        // La foto anterior se borra solo cuando el cambio quedó confirmado
        if ($avatarNuevo && $avatarAnterior) {
            Storage::disk('public')->delete($avatarAnterior);
        }

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'data'    => new PerfilResource($usuario->refresh()->load(self::RELACIONES)),
        ], 200);
    }

    // PATCH /api/admin/perfil/password
    public function actualizarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        $usuario = $this->usuarioPanel();

        if (!Hash::check($request->password_actual, $usuario->usu_passwd)) {
            return response()->json([
                'errors' => ['password_actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        if (Hash::check($request->password_nuevo, $usuario->usu_passwd)) {
            return response()->json([
                'errors' => ['password_nuevo' => ['La nueva contraseña debe ser distinta de la actual.']],
            ], 422);
        }

        HistorialPassword::create([
            'hpa_usuid'  => $usuario->usu_id,
            'hpa_pasant' => $usuario->usu_passwd,
            'hpa_iporig' => $request->ip(),
        ]);

        $usuario->update(['usu_passwd' => bcrypt($request->password_nuevo)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente'], 200);
    }

    /** Usuario autenticado del panel. Los clientes usan su propio perfil en la tienda. */
    private function usuarioPanel(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol');

        abort_if($usuario->rol?->rol_descri === 'Cliente', 403, 'Este recurso es exclusivo del panel administrativo.');

        return $usuario;
    }
}