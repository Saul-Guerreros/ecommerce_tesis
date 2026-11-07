<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalogo\ResenaPublicaResource;
use App\Models\PublicacionWeb;
use App\Services\Catalogo\ResenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reseñas visibles de un producto (público, sin sesión).
 *
 * GET /api/tienda/producto/{slug}/resenas?orden=recientes|mejor|peor&estrellas=1..5&page=N
 */
class ResenaPublicaController extends Controller
{
    public function __construct(private ResenaService $servicio)
    {
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $pub = PublicacionWeb::publicadas()
            ->where('pub_slug', $slug)
            ->with('producto')
            ->firstOrFail();

        $grupo = $this->servicio->grupoDe($pub->producto);

        $orden = in_array($request->query('orden'), ResenaService::ORDENES, true)
            ? $request->query('orden')
            : 'recientes';

        $estrellas = (int) $request->query('estrellas');
        $estrellas = $estrellas >= 1 && $estrellas <= 5 ? $estrellas : null;

        $resenas = $this->servicio->listarPublicas($grupo, $orden, $estrellas);

        return response()->json([
            'data' => ResenaPublicaResource::collection($resenas->items()),
            'meta' => [
                'current_page' => $resenas->currentPage(),
                'last_page'    => $resenas->lastPage(),
                'total'        => $resenas->total(),
            ],
            'resumen' => $this->servicio->resumen($grupo),
        ]);
    }
	
	    /**
     * GET /api/tienda/resenas/destacadas
     *
     * Testimonios del Home: reseñas reales, visibles, de 4-5 estrellas, con
     * comentario útil, de productos publicados. Una por cliente, las más
     * recientes primero.
     */
    public function destacadas(): JsonResponse
    {
        $resenas = \App\Models\Catalogo\Resena::visibles()
            ->where('res_califi', '>=', 4)
            ->whereRaw('char_length(res_coment) >= 20')
            ->whereHas('producto.publicacionWeb', fn ($q) => $q->publicadas())
            ->with(['cliente.persona', 'producto.publicacionWeb'])
            ->orderByDesc('res_creado')
            ->limit(30)
            ->get()
            ->unique('res_cliid')
            ->take(8)
            ->values();

        return response()->json([
            'data' => $resenas->map(fn ($r) => array_merge(
                (new ResenaPublicaResource($r))->toArray(request()),
                ['slug' => $r->producto?->publicacionWeb?->pub_slug],
            )),
        ]);
    }
}