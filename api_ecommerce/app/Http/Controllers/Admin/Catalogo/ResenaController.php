<?php

namespace App\Http\Controllers\Admin\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\CambiarEstadoResenaRequest;
use App\Http\Requests\Catalogo\ResponderResenaRequest;
use App\Http\Resources\Catalogo\ResenaAdminResource;
use App\Models\Catalogo\Resena;
use App\Services\Catalogo\ResenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moderación de reseñas (Panel Admin). Permiso: resenas.gestionar.
 * No hay "store": las reseñas solo las crean los clientes con compra entregada.
 */
class ResenaController extends Controller
{
    public function __construct(private ResenaService $servicio)
    {
    }

    // GET /api/admin/resenas?search=&estado=&calificacion=&respuesta=con|sin&page=&per_page=
    public function index(Request $request): JsonResponse
    {
        $query = Resena::query()->with([
            'producto.publicacionWeb', 'cliente.persona', 'pedido', 'usuarioRespuesta.persona',
        ]);

        if ($search = trim((string) $request->query('search'))) {
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $query->where(function ($q) use ($like) {
                $q->where('res_titulo', 'ilike', $like)
                    ->orWhere('res_coment', 'ilike', $like)
                    ->orWhereHas('pedido', fn ($p) => $p->where('ped_numero', 'ilike', $like))
                    ->orWhereHas('producto.publicacionWeb', fn ($p) => $p->where('pub_titulo', 'ilike', $like))
                    // where() anidado: un orWhere suelto dentro de whereHas
                    // rompería la condición que vincula persona con cliente.
                    ->orWhereHas('cliente.persona', fn ($p) => $p->where(fn ($w) => $w
                        ->where('per_nombr1', 'ilike', $like)
                        ->orWhere('per_apell1', 'ilike', $like)
                        ->orWhere('per_cedula', 'ilike', $like)));
            });
        }

        if (in_array($request->query('estado'), ['0', '1'], true)) {
            $query->where('res_estado', (int) $request->query('estado'));
        }

        $calificacion = (int) $request->query('calificacion');
        if ($calificacion >= 1 && $calificacion <= 5) {
            $query->where('res_califi', $calificacion);
        }

        match ($request->query('respuesta')) {
            'con'   => $query->whereNotNull('res_respue'),
            'sin'   => $query->whereNull('res_respue'),
            default => null,
        };

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);
        $resenas = $query->orderByDesc('res_creado')->paginate($perPage);

        return response()->json([
            'data' => ResenaAdminResource::collection($resenas->items()),
            'meta' => [
                'current_page' => $resenas->currentPage(),
                'last_page'    => $resenas->lastPage(),
                'per_page'     => $resenas->perPage(),
                'total'        => $resenas->total(),
            ],
            // Contadores generales (sin filtros) para la cabecera del listado.
            'resumen' => [
                'total'         => Resena::count(),
                'ocultas'       => Resena::where('res_estado', Resena::OCULTA)->count(),
                'sin_respuesta' => Resena::visibles()->whereNull('res_respue')->count(),
            ],
        ]);
    }

    // PATCH /api/admin/resenas/{id}/estado  { res_motocu }
    public function cambiarEstado(CambiarEstadoResenaRequest $request, int $id): JsonResponse
    {
        $resena = $this->servicio->cambiarVisibilidad(
            Resena::findOrFail($id),
            $request->validated('res_motocu'),
        );

        return response()->json([
            'message' => $resena->esVisible()
                ? 'La reseña vuelve a estar visible en la tienda.'
                : 'La reseña se ocultó de la tienda.',
        ]);
    }

    // PUT /api/admin/resenas/{id}/respuesta  { res_respue }
    public function responder(ResponderResenaRequest $request, int $id): JsonResponse
    {
        $resena = $this->servicio->responder(
            Resena::findOrFail($id),
            $request->validated('res_respue'),
            auth('api')->user(),
        );

        return response()->json([
            'message' => $resena->res_respue ? 'Respuesta publicada.' : 'Respuesta eliminada.',
        ]);
    }

    // DELETE /api/admin/resenas/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->servicio->eliminar(Resena::findOrFail($id));

        return response()->json(['message' => 'Reseña eliminada.']);
    }
}