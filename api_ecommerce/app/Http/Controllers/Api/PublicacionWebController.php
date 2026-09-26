<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicacionWebRequest;
use App\Http\Resources\PublicacionWebResource;
use App\Models\ImagenProducto;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\VitrinaProducto;
use Illuminate\Support\Facades\Storage;

class PublicacionWebController extends Controller
{
    /**
     * GET /api/publicaciones
     * Listado general para el apartado "Publicación Web" del dashboard.
     * Soporta: ?search=, ?categoria=, ?estado=, ?per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = PublicacionWeb::query()
            ->with(['producto', 'categoria', 'imagenes']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pub_titulo', 'like', "%{$search}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('prd_codinv', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('categoria')) {
            $query->where('pub_catid', $request->query('categoria'));
        }

        if ($request->filled('estado')) {
            $query->where('pub_estado', $request->query('estado'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $publicaciones = $query->orderByDesc('pub_id')->paginate($perPage);

        return response()->json([
            'data' => PublicacionWebResource::collection($publicaciones->items()),
            'meta' => [
                'current_page' => $publicaciones->currentPage(),
                'last_page'    => $publicaciones->lastPage(),
                'per_page'     => $publicaciones->perPage(),
                'total'        => $publicaciones->total(),
            ],
        ]);
    }

    /**
     * GET /api/productos/{producto}/publicacion
     * Devuelve la publicación del producto, o null si todavía no se publicó.
     */
    public function show(Producto $producto): JsonResponse
    {
        $publicacion = $producto->publicacionWeb()
        ->with(['producto', 'categoria', 'imagenes', 'especificaciones', 'vitrinas'])
        ->first();
 
    return response()->json([
        'data' => $publicacion ? new PublicacionWebResource($publicacion) : null,
    ]);
    }

    public function guardar(StorePublicacionWebRequest $request, Producto $producto): JsonResponse
{
    $esNueva = ! $producto->publicacionWeb()->exists();
 
    $publicacion = DB::transaction(function () use ($request, $producto) {
        // Los 6 campos de vitrina NO son columnas de publicacion_web,
        // así que se excluyen del updateOrCreate y se procesan aparte.
        $datos = $request->safe()->except([
            'imagenes',
            'featured_activo', 'featured_orden',
            'trending_activo', 'trending_orden',
            'selling_activo', 'selling_orden',
        ]);
 
        $publicacion = PublicacionWeb::updateOrCreate(
            ['pub_prdid' => $producto->prd_id],
            $datos
        );
 
        $this->guardarImagenes($publicacion, $request);
        $this->guardarVitrina($publicacion, $request);
 
        return $publicacion;
    });
 
    $publicacion->load(['categoria', 'imagenes', 'vitrinas']);
 
    return response()->json([
        'message' => $esNueva
            ? 'Publicación web creada correctamente.'
            : 'Publicación web actualizada correctamente.',
        'data' => new PublicacionWebResource($publicacion),
    ], $esNueva ? 201 : 200);
}
 
/**
 * Sincroniza vitrina_producto según los toggles del formulario:
 * activo=true con un orden -> upsert de esa sección;
 * activo=false (o sin orden) -> se borra esa sección si existía.
 */
private function guardarVitrina(PublicacionWeb $publicacion, Request $request): void
{
    foreach (VitrinaProducto::SECCIONES as $seccion) {
        $activo = $request->boolean("{$seccion}_activo");
        $orden = $request->input("{$seccion}_orden");
 
        if ($activo && $orden) {
            VitrinaProducto::updateOrCreate(
                ['vip_pubid' => $publicacion->pub_id, 'vip_seccion' => $seccion],
                ['vip_orden' => $orden]
            );
        } else {
            VitrinaProducto::where('vip_pubid', $publicacion->pub_id)
                ->where('vip_seccion', $seccion)
                ->delete();
        }
    }
}

    /**
     * DELETE /api/productos/{producto}/publicacion/imagenes/{imagen}
     */
    public function eliminarImagen(Producto $producto, ImagenProducto $imagen): JsonResponse
    {
        $publicacion = $producto->publicacionWeb()->firstOrFail();

        abort_if($imagen->img_pubid !== $publicacion->pub_id, 404);

        Storage::disk('public')->delete($imagen->img_url);
        $imagen->delete();

        return response()->json([
            'message' => 'Imagen eliminada correctamente.',
        ]);
    }

    private function guardarImagenes(PublicacionWeb $publicacion, Request $request): void
    {
        if (! $request->hasFile('imagenes')) {
            return;
        }

        $ordenActual = $publicacion->imagenes()->max('img_orden') ?? 0;
        $tieneImagenPrincipal = $publicacion->imagenes()->where('img_princi', 1)->exists();

        foreach ($request->file('imagenes') as $index => $archivo) {
            $ruta = $archivo->store('productos', 'public');

            ImagenProducto::create([
                'img_pubid'  => $publicacion->pub_id,
                'img_url'    => $ruta,
                'img_orden'  => ++$ordenActual,
                'img_princi' => (! $tieneImagenPrincipal && $index === 0) ? 1 : 0,
            ]);

            $tieneImagenPrincipal = true;
        }
    }
}
