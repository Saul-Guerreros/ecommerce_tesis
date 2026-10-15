<?php

namespace App\Http\Controllers\Admin\Seguridad;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seguridad\HistorialPasswordResource;
use App\Http\Resources\Seguridad\IntentoSesionResource;
use App\Http\Resources\Seguridad\SesionResource;
use App\Http\Resources\Seguridad\TokenVerificacionResource;
use App\Models\HistorialPassword;
use App\Models\IntentoSesion;
use App\Models\Sesion;
use App\Models\TokenVerificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pantallas de Auditoría (Seguridad y Acceso). SOLO LECTURA:
 * no hay store/update/destroy porque estos registros los genera el sistema
 * (login, cambio de contraseña, verificación de correo) y no deben alterarse.
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 25;

    /** Relaciones mínimas: nunca se cargan columnas con hashes. */
    private const USUARIO_COLS = 'usuario:usu_id,usu_login,usu_perid';
    private const PERSONA_COLS = 'usuario.persona:per_id,per_nombr1,per_nombr2,per_apell1,per_apell2';

    private function validarFiltros(Request $request): void
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'desde'  => ['nullable', 'date_format:Y-m-d'],
            'hasta'  => ['nullable', 'date_format:Y-m-d'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** Filtra por login o por nombre/documento de la persona dueña del usuario. */
    private function buscarUsuario(Builder $query, string $search): Builder
    {
        $like = "%{$search}%";

        return $query->whereHas('usuario', function ($u) use ($like) {
            $u->where('usu_login', 'ilike', $like)
              ->orWhereHas('persona', fn ($p) =>
                  $p->where('per_nombr1', 'ilike', $like)
                    ->orWhere('per_apell1', 'ilike', $like)
                    ->orWhere('per_cedula', 'ilike', $like));
        });
    }

    private function filtrarFechas(Builder $query, Request $request, string $columna): Builder
    {
        return $query
            ->when($request->filled('desde'), fn ($q) => $q->whereDate($columna, '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate($columna, '<=', $request->hasta));
    }

    // GET /api/admin/seguridad/historial-password?search=&desde=&hasta=&page=
    public function historialPassword(Request $request): JsonResponse
    {
        $this->validarFiltros($request);

        $query = HistorialPassword::query()
            ->select('hpa_id', 'hpa_iporig', 'hpa_creado', 'hpa_usuid') // sin hpa_pasant
            ->with([self::USUARIO_COLS, self::PERSONA_COLS])
            ->when($request->filled('search'), fn ($q) => $this->buscarUsuario($q, $request->search));

        $this->filtrarFechas($query, $request, 'hpa_creado');

        $pagina = $query->orderByDesc('hpa_creado')->orderByDesc('hpa_id')->paginate(self::POR_PAGINA);

        return response()->json([
            'total' => $pagina->total(),
            'data'  => HistorialPasswordResource::collection($pagina->items()),
        ], 200);
    }

    // GET /api/admin/seguridad/sesiones?search=&estado=activas|expiradas|revocadas|todas&page=
    public function sesiones(Request $request): JsonResponse
    {
        $this->validarFiltros($request);
        $request->validate(['estado' => ['nullable', 'in:activas,expiradas,revocadas,todas']]);

        $query = Sesion::query()
            ->select('ses_id', 'ses_usuid', 'ses_iporig', 'ses_userag', 'ses_fecexp', 'ses_revoca', 'ses_creado') // sin ses_tokhas
            ->with([self::USUARIO_COLS, self::PERSONA_COLS])
            ->when($request->filled('search'), fn ($q) => $this->buscarUsuario($q, $request->search));

        match ($request->input('estado', 'activas')) {
            'activas'   => $query->activas(),
            'expiradas' => $query->where('ses_revoca', 0)->where('ses_fecexp', '<=', now()),
            'revocadas' => $query->where('ses_revoca', 1),
            default     => null, // todas
        };

        $pagina = $query->orderByDesc('ses_creado')->orderByDesc('ses_id')->paginate(self::POR_PAGINA);

        return response()->json([
            'total'   => $pagina->total(),
            'resumen' => ['activas_ahora' => Sesion::activas()->count()],
            'data'    => SesionResource::collection($pagina->items()),
        ], 200);
    }

    // GET /api/admin/seguridad/intentos-sesion?search=&resultado=1|0&desde=&hasta=&page=
    public function intentosSesion(Request $request): JsonResponse
    {
        $this->validarFiltros($request);
        $request->validate(['resultado' => ['nullable', 'in:0,1']]);

        $query = IntentoSesion::query()
            ->with([self::USUARIO_COLS, self::PERSONA_COLS])
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = "%{$request->search}%";
                $q->where(fn ($s) => $s->where('ise_logusa', 'ilike', $like)
                                       ->orWhere('ise_iporig', 'ilike', $like));
            })
            ->when($request->filled('resultado'), fn ($q) => $q->where('ise_result', $request->resultado));

        $this->filtrarFechas($query, $request, 'ise_creado');

        // Fallidos dentro del filtro actual (para el resumen del encabezado)
        $fallidos = (clone $query)->where('ise_result', 0)->count();

        $pagina = $query->orderByDesc('ise_creado')->orderByDesc('ise_id')->paginate(self::POR_PAGINA);

        return response()->json([
            'total'   => $pagina->total(),
            'resumen' => ['fallidos' => $fallidos],
            'data'    => IntentoSesionResource::collection($pagina->items()),
        ], 200);
    }

    // GET /api/admin/seguridad/tokens?search=&tipo=&estado=vigente|usado|expirado&page=
    public function tokens(Request $request): JsonResponse
    {
        $this->validarFiltros($request);
        $request->validate([
            'tipo'   => ['nullable', 'in:' . TokenVerificacion::TIPO_VERIFICACION_EMAIL . ',' . TokenVerificacion::TIPO_RECUPERACION_PASSWORD],
            'estado' => ['nullable', 'in:vigente,usado,expirado'],
        ]);

        $query = TokenVerificacion::query()
            ->select('tkv_id', 'tkv_usuid', 'tkv_tipo', 'tkv_fecexp', 'tkv_usado', 'tkv_creado') // sin tkv_tokhas
            ->with([self::USUARIO_COLS, self::PERSONA_COLS])
            ->when($request->filled('search'), fn ($q) => $this->buscarUsuario($q, $request->search))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tkv_tipo', $request->tipo));

        match ($request->input('estado')) {
            'vigente'  => $query->where('tkv_usado', 0)->where('tkv_fecexp', '>', now()),
            'usado'    => $query->where('tkv_usado', 1),
            'expirado' => $query->where('tkv_usado', 0)->where('tkv_fecexp', '<=', now()),
            default    => null,
        };

        $pagina = $query->orderByDesc('tkv_creado')->orderByDesc('tkv_id')->paginate(self::POR_PAGINA);

        return response()->json([
            'total'   => $pagina->total(),
            'resumen' => [
                'vigentes' => TokenVerificacion::where('tkv_usado', 0)->where('tkv_fecexp', '>', now())->count(),
            ],
            'data'    => TokenVerificacionResource::collection($pagina->items()),
        ], 200);
    }
}
