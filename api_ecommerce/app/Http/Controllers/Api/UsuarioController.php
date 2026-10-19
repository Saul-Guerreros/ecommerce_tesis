<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Cargo;
use App\Models\Configuracion\Sucursal;
use App\Models\Funcionario;
use App\Models\HistorialPassword;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Models\UsuarioSucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestion de usuarios del panel (funcionarios: Administrador y Vendedor).
 * Los clientes del ecommerce NO se gestionan aqui (ver AuthController::register).
 *
 * Rutas protegidas por 'permiso:usuarios.ver' / 'permiso:usuarios.gestionar'
 * (ver routes/api.php y RolPermisoSeeder).
 */
class UsuarioController extends Controller
{
    private const RELACIONES = [
        'rol',
        'persona.funcionario.cargo',
        'sucursales'
    ];

    /**
     * GET /api/admin/usuarios
     * Soporta: ?search=, ?estado=, ?rol_id=, ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Usuario::query()
            ->with(self::RELACIONES)->whereHas('rol', fn ($q) => $q->conAccesoPanel());

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'usu_login',
                    'like',
                    "%{$search}%"
                )->orWhereHas('persona', function ($qp) use ($search) {
                    $qp->where(
                        'per_nombr1',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'per_apell1',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'per_cedula',
                        'like',
                        "%{$search}%"
                    );
                });
            });
        }

        if ($request->filled('estado')) {
            $query->where(
                'usu_estado',
                $request->query('estado')
            );
        }

        if ($request->filled('rol_id')) {
            $query->where(
                'usu_rolid',
                $request->query('rol_id')
            );
        }

        $perPage = (int) $request->query('per_page', 10);

        $usuarios = $query
            ->orderByDesc('usu_id')
            ->paginate($perPage);

        return response()->json([
            'data' => UsuarioResource::collection(
                $usuarios->items()
            ),
            'meta' => [
                'current_page' => $usuarios->currentPage(),
                'last_page' => $usuarios->lastPage(),
                'per_page' => $usuarios->perPage(),
                'total' => $usuarios->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/usuarios/catalogos
     * Roles (sin Cliente), cargos activos y sucursales activas,
     * para los combos/checklist del formulario.
     */
    public function catalogos(): JsonResponse
    {
        return response()->json([
            'roles' => Rol::conAccesoPanel()
                ->where('rol_estado', 1)
                ->get(['rol_id', 'rol_descri']),

            'cargos' => Cargo::where(
                'crg_estado',
                1
            )->get([
                'crg_id',
                'crg_descr'
            ]),

            'sucursales' => Sucursal::where(
                'suc_estado',
                1
            )
                ->orderBy('suc_nombre')
                ->get([
                    'suc_id',
                    'suc_nombre'
                ]),
        ]);
    }

    /**
     * GET /api/admin/usuarios/{usuario}
     */
    public function show(Usuario $usuario): JsonResponse
    {
        $usuario->load(self::RELACIONES);

        return response()->json([
            'data' => new UsuarioResource($usuario)
        ]);
    }

    /**
     * POST /api/admin/usuarios
     */
    public function store(
        StoreUsuarioRequest $request
    ): JsonResponse {
        $rol = Rol::findOrFail($request->rol_id);

        if ($rol->rol_descri === 'Cliente') {
            return response()->json([
                'message' => 'El rol Cliente no se gestiona desde este panel.'
            ], 422);
        }

        $tipoDocumento = TipoDocumento::where(
            'tpd_codigo',
            'CI'
        )->first() ?? TipoDocumento::first();

        $usuario = DB::transaction(
            function () use (
                $request,
                $rol,
                $tipoDocumento
            ) {
                $persona = Persona::create([
                    'per_tpdid' => $tipoDocumento->tpd_id,
                    'per_nombr1' => $request->nombre1,
                    'per_nombr2' => $request->nombre2,
                    'per_apell1' => $request->apellido1,
                    'per_apell2' => $request->apellido2,
                    'per_cedula' => $request->cedula,
                    'per_fechna' => $request->fecha_nacimiento,
                    'per_estado' => 1,
                ]);

                Funcionario::create([
                    'fun_perid' => $persona->per_id,
                    'fun_crgid' => $request->cargo_id,
                    'fun_rolid' => $rol->rol_id,
                    'fun_fecing' => now()->toDateString(),
                    'fun_estado' => 1,
                ]);

                // A un funcionario lo da de alta un Administrador,
                // por lo que entra directo como ACTIVO
                // (no pasa por verificacion de email).
                $usuario = Usuario::create([
                    'usu_perid' => $persona->per_id,
                    'usu_rolid' => $rol->rol_id,
                    'usu_login' => $request->email,
                    'usu_passwd' => bcrypt($request->password),
                    'usu_estado' => Usuario::ESTADO_ACTIVO,
                ]);

                $this->sincronizarSucursales(
                    $usuario,
                    $request->sucursales,
                    $request->sucursal_principal
                );

                return $usuario;
            }
        );

        $usuario->load(self::RELACIONES);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => new UsuarioResource($usuario),
        ], 201);
    }

    /**
     * PUT /api/admin/usuarios/{usuario}
     */
    public function update(
        UpdateUsuarioRequest $request,
        Usuario $usuario
    ): JsonResponse {
        $rol = Rol::findOrFail($request->rol_id);

        if ($rol->rol_descri === 'Cliente') {
            return response()->json([
                'message' => 'El rol Cliente no se gestiona desde este panel.'
            ], 422);
        }

        DB::transaction(function () use (
            $request,
            $usuario,
            $rol
        ) {
            $usuario->persona->update([
                'per_nombr1' => $request->nombre1,
                'per_nombr2' => $request->nombre2,
                'per_apell1' => $request->apellido1,
                'per_apell2' => $request->apellido2,
                'per_cedula' => $request->cedula,
                'per_fechna' => $request->fecha_nacimiento,
            ]);

            if ($usuario->persona->funcionario) {
                 $usuario->persona->funcionario->update([
                    'fun_crgid' => $request->cargo_id,
                    'fun_rolid' => $rol->rol_id,
                ]);
            } else {
                 Funcionario::create([
                    'fun_perid' => $usuario->persona->per_id,
                    'fun_crgid' => $request->cargo_id,
                    'fun_rolid' => $rol->rol_id,
                    'fun_fecing' => now()->toDateString(),
                    'fun_estado' => 1,
                ]);
            }

            $datosUsuario = [
                'usu_login' => $request->email,
                'usu_rolid' => $rol->rol_id,
            ];

            if ($request->filled('password')) {
                HistorialPassword::create([
                    'hpa_usuid' => $usuario->usu_id,
                    'hpa_pasant' => $usuario->usu_passwd,
                    'hpa_iporig' => $request->ip(),
                ]);

                $datosUsuario['usu_passwd'] =
                    bcrypt($request->password);
            }

            $usuario->update($datosUsuario);

            $this->sincronizarSucursales(
                $usuario,
                $request->sucursales,
                $request->sucursal_principal
            );
        });

        $usuario->load(self::RELACIONES);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => new UsuarioResource($usuario),
        ]);
    }

    /**
     * Reemplaza el set completo de sucursales asignadas al usuario.
     * Estrategia simple (delete + recreate) porque el volumen por usuario
     * es chico (unas pocas sucursales) y evita tener que diffear altas/bajas.
     *
     * @param int[] $sucursalIds
     * @param int|null $principalId
     * Si viene null y hay una sola sucursal,
     * esa queda como principal automaticamente.
     */
    private function sincronizarSucursales(
        Usuario $usuario,
        array $sucursalIds,
        ?int $principalId
    ): void {
        UsuarioSucursal::where(
            'uss_usuid',
            $usuario->usu_id
        )->delete();

        if (
            count($sucursalIds) === 1 &&
            $principalId === null
        ) {
            $principalId = (int) $sucursalIds[0];
        }

        foreach ($sucursalIds as $sucId) {
            UsuarioSucursal::create([
                'uss_usuid' => $usuario->usu_id,
                'uss_sucid' => $sucId,
                'uss_princi' =>
                    ((int) $sucId === (int) $principalId)
                        ? 1
                        : 0,
                'uss_estado' => 1,
            ]);
        }
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/estado
     * Activar, desactivar o desbloquear una cuenta (usu_estado).
     * No hay borrado fisico: usuario/persona quedan referenciados por
     * sesion/intento_sesion/historial_password/token_verificacion.
     */
    public function cambiarEstado(
        Request $request,
        Usuario $usuario
    ): JsonResponse {
        $request->validate([
            'estado' => 'required|in:ACTIVO,INACTIVO,BLOQUEADO',
        ]);

        if (
            $usuario->usu_id === auth('api')->id() &&
            $request->estado !== Usuario::ESTADO_ACTIVO
        ) {
            return response()->json([
                'message' =>
                    'No puedes desactivar o bloquear tu propia cuenta.'
            ], 422);
        }

        $usuario->update([
            'usu_estado' => $request->estado
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => new UsuarioResource(
                $usuario->fresh(self::RELACIONES)
            ),
        ]);
    }
}