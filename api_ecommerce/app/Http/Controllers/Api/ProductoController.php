<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\ImagenProducto;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    /**
     * GET /api/productos
     * Soporta: ?search=, ?categoria=, ?marca=, ?estado=, ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['categoria', 'marca', 'imagenes']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('prd_titulo', 'like', "%{$search}%")
                    ->orWhere('prd_codinv', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria')) {
            $query->where('prd_catid', $request->query('categoria'));
        }

        if ($request->filled('marca')) {
            $query->where('prd_marid', $request->query('marca'));
        }

        if ($request->filled('estado')) {
            $query->where('prd_estado', $request->query('estado'));
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
     */
    public function store(StoreProductoRequest $request): JsonResponse
    {
        $producto = DB::transaction(function () use ($request) {
            $producto = Producto::create($request->safe()->except('imagenes'));

            $this->guardarImagenes($producto, $request);

            return $producto;
        });

        $producto->load(['categoria', 'marca', 'imagenes']);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => new ProductoResource($producto),
        ], 201);
    }

    /**
     * GET /api/productos/{producto}
     */
    public function show(Producto $producto): JsonResponse
    {
        $producto->load(['categoria', 'marca', 'imagenes']);

        return response()->json([
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * PUT/PATCH /api/productos/{producto}
     */
    public function update(UpdateProductoRequest $request, Producto $producto): JsonResponse
    {
        DB::transaction(function () use ($request, $producto) {
            $producto->update($request->safe()->except('imagenes'));

            $this->guardarImagenes($producto, $request);
        });

        $producto->load(['categoria', 'marca', 'imagenes']);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => new ProductoResource($producto),
        ]);
    }

    /**
     * DELETE /api/productos/{producto}
     */
    public function destroy(Producto $producto): JsonResponse
    {
        DB::transaction(function () use ($producto) {
            foreach ($producto->imagenes as $imagen) {
                Storage::disk('public')->delete($imagen->img_url);
                $imagen->delete();
            }

            $producto->delete();
        });

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    /**
     * DELETE /api/productos/{producto}/imagenes/{imagen}
     */
    public function eliminarImagen(Producto $producto, ImagenProducto $imagen): JsonResponse
    {
        abort_if($imagen->img_prdid !== $producto->prd_id, 404);

        Storage::disk('public')->delete($imagen->img_url);
        $imagen->delete();

        return response()->json([
            'message' => 'Imagen eliminada correctamente.',
        ]);
    }

    private function guardarImagenes(Producto $producto, Request $request): void
    {
        if (! $request->hasFile('imagenes')) {
            return;
        }

        $ordenActual = $producto->imagenes()->max('img_orden') ?? 0;
        $tieneImagenPrincipal = $producto->imagenes()->where('img_princi', 1)->exists();

        foreach ($request->file('imagenes') as $index => $archivo) {
            $ruta = $archivo->store('productos', 'public');

            ImagenProducto::create([
                'img_prdid'  => $producto->prd_id,
                'img_url'    => $ruta,
                'img_orden'  => ++$ordenActual,
                'img_princi' => (! $tieneImagenPrincipal && $index === 0) ? 1 : 0,
            ]);

            $tieneImagenPrincipal = true;
        }
    }
}
