<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\CambiarPasswordRequest;
use App\Http\Requests\Cliente\PerfilClienteRequest;
use App\Http\Resources\Cliente\PerfilClienteResource;
use App\Models\HistorialPassword;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * "Mi perfil" del ecommerce: autogestión de datos personales, avatar,
 * un contacto celular, RUC y contraseña. Exclusivo para usuarios con
 * rol Cliente (ver AuthController::login_ecommerce).
 *
 * Las direcciones se gestionan aparte, en DireccionClienteController.
 */
class PerfilClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /** GET /api/tienda/perfil */
    public function show(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();

        $usuario->load([
            'persona.contactos',
            'persona.cliente.tipoCliente',
            'persona.tipoDocumento',
            'persona.direcciones.barrio.ciudad',
            'persona.direcciones.calle',
        ]);

        return response()->json([
            'data' => new PerfilClienteResource($usuario),
        ]);
    }

    /**
     * POST /api/tienda/perfil
     * Se registra como POST (no PUT) a propósito: PHP no parsea bien el
     * body multipart/form-data (necesario para el avatar) en verbos PUT/PATCH
     * nativos. Mismo criterio que ya usa SliderController para sus imágenes.
     */
    public function update(PerfilClienteRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $data = $request->validated();

        DB::transaction(function () use ($usuario, $data, $request) {
            $persona = $usuario->persona;

            $persona->update([
                'per_nombr1' => $data['per_nombr1'],
                'per_nombr2' => $data['per_nombr2'] ?? null,
                'per_apell1' => $data['per_apell1'],
                'per_apell2' => $data['per_apell2'] ?? null,
                'per_fechna' => $data['per_fechna'] ?? null,
            ]);

            if ($request->hasFile('avatar')) {
                if ($persona->per_avatar) {
                    Storage::disk('public')->delete($persona->per_avatar);
                }
                $persona->update([
                    'per_avatar' => $request->file('avatar')->store('avatars', 'public'),
                ]);
            }

            if ($request->filled('celular')) {
                $persona->contactos()->updateOrCreate(
                    ['con_tipo' => 'CELULAR'],
                    ['con_valor' => $data['celular'], 'con_estado' => 1]
                );
            }

            if (array_key_exists('cli_ruc', $data)) {
                $persona->cliente?->update(['cli_ruc' => $data['cli_ruc']]);
            }
        });

        $usuario->refresh()->load([
            'persona.contactos',
            'persona.cliente.tipoCliente',
            'persona.tipoDocumento',
            'persona.direcciones.barrio.ciudad',
            'persona.direcciones.calle',
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'data' => new PerfilClienteResource($usuario),
        ]);
    }

    /** PATCH /api/tienda/perfil/password */
    public function actualizarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();

        if (!Hash::check($request->password_actual, $usuario->usu_passwd)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
            ], 422);
        }

        HistorialPassword::create([
            'hpa_usuid'  => $usuario->usu_id,
            'hpa_pasant' => $usuario->usu_passwd,
            'hpa_iporig' => $request->ip(),
        ]);

        $usuario->update(['usu_passwd' => bcrypt($request->password_nuevo)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}