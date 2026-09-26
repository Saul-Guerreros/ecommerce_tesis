<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    /**
     * GET /api/productos
     * Soporta: ?search=, ?categoria=, ?marca=, ?estado=, ?estado_publicacion=, ?per_page=
     *
     * NOTA: "categoria" ahora vive en publicacion_web (pub_catid), por eso
     * el filtro se resuelve con whereHas. "estado" sigue siendo el estado
     * de INVENTARIO (prd_estado: activo/inactivo). "estado_publicacion"
     * (sin_publicar | borrador | publicado | archivado) filtra por publicacion_web.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['marca', 'tipoIva', 'unidadMedida', 'publicacionWeb.categoria', 'publicacionWeb.imagenes','padre','variantes.publicacionWeb.imagenes']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('prd_codinv', 'like', "%{$search}%")
                    ->orWhereHas('publicacionWeb', fn ($p) => $p->where('pub_titulo', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('categoria')) {
            $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_catid', $request->query('categoria')));
        }

        if ($request->filled('marca')) {
            $query->where('prd_marid', $request->query('marca'));
        }

        if ($request->filled('estado')) {
            $query->where('prd_estado', $request->query('estado'));
        }

        // Filtro por estado de PUBLICACIÓN (distinto del estado de inventario
        // de arriba). Usado por la sección "Publicación Web" del dashboard.
        if ($request->filled('estado_publicacion')) {
            match ($request->query('estado_publicacion')) {
                'sin_publicar' => $query->whereDoesntHave('publicacionWeb'),
                'borrador'     => $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 0)),
                'publicado'    => $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 1)),
                'archivado'    => $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 2)),
                default        => null,
            };
        }

        $perPage = (int) $request->query('per_page', 10);
        $productos = $query->orderByDesc('prd_id')->paginate($perPage);

        return response()->json([
            'data' => ProductoResource::collection($productos->items()),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page'    => $productos->lastPage(),
                'per_page'     => $productos->perPage(),
                'total'        => $productos->total(),
            ],
        ]);
    }

    /**
     * POST /api/productos
     * Solo datos de inventario/compra. La publicación web (categoría,
     * título, imágenes) se crea aparte, en /productos/{producto}/publicacion.
     */
    public function store(StoreProductoRequest $request): JsonResponse
    {
        $producto = Producto::create($request->validated());

        $producto->load(['marca', 'tipoIva', 'unidadMedida', 'publicacionWeb.categoria', 'publicacionWeb.imagenes','padre','variantes.publicacionWeb.imagenes']);

        return response()->json([
            'message' => 'Producto creado correctamente. Ahora puedes publicarlo en la web.',
            'data' => new ProductoResource($producto),
        ], 201);
    }

    /**
     * GET /api/productos/{producto}
     */
    public function show(Producto $producto): JsonResponse
    {
        $producto->load(['marca', 'tipoIva', 'unidadMedida', 'publicacionWeb.categoria', 'publicacionWeb.imagenes','padre','variantes.publicacionWeb.imagenes']);

        return response()->json([
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * PUT/PATCH /api/productos/{producto}
     */
    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto->update($request->validated());

        $producto->load(['marca', 'tipoIva', 'unidadMedida', 'publicacionWeb.categoria', 'publicacionWeb.imagenes','padre','variantes.publicacionWeb.imagenes']);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * DELETE /api/productos/{producto}
     * Al borrar el producto se borra en cascada su publicación web
     * (FK pub_prdid ON DELETE CASCADE) y, con ella, sus imágenes en BD.
     * Los archivos físicos hay que borrarlos aparte, antes del delete.
     */
    public function destroy(Producto $producto): JsonResponse
    {
        $producto->loadMissing('publicacionWeb.imagenes');

        foreach ($producto->publicacionWeb?->imagenes ?? [] as $imagen) {
            Storage::disk('public')->delete($imagen->img_url);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
