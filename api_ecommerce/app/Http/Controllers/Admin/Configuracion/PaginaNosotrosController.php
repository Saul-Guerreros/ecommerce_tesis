<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ImagenNosotrosRequest;
use App\Http\Requests\Configuracion\PaginaNosotrosRequest;
use App\Http\Resources\Configuracion\PaginaNosotrosResource;
use App\Models\Configuracion\PaginaNosotros;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Página "Sobre nosotros" (singleton): sin id en la ruta ni store/destroy.
 * La imagen va por endpoints aparte para que el guardado del texto sea
 * JSON puro y no haya que reenviar el archivo en cada cambio.
 */
class PaginaNosotrosController extends Controller
{
    // GET /api/admin/pagina-nosotros
    public function show(): JsonResponse
    {
        return response()->json(['data' => new PaginaNosotrosResource(PaginaNosotros::actual())]);
    }

    // PUT /api/admin/pagina-nosotros
    public function update(PaginaNosotrosRequest $request): JsonResponse
    {
        $pagina = PaginaNosotros::actual();

        $datos = $request->validated();
        $datos['pno_valores'] = $datos['pno_valores'] ?? [];
        $datos['pno_cifras']  = $datos['pno_cifras'] ?? [];
        $datos['pno_usuid']   = auth('api')->id();

        $pagina->update($datos);

        return response()->json([
            'message' => 'Página "Sobre nosotros" actualizada.',
            'data'    => new PaginaNosotrosResource($pagina->fresh()),
        ]);
    }

    // POST /api/admin/pagina-nosotros/imagen  (multipart: imagen)
    public function subirImagen(ImagenNosotrosRequest $request): JsonResponse
    {
        $pagina   = PaginaNosotros::actual();
        $anterior = $pagina->pno_imagen;

        // putFile genera un nombre aleatorio: no se usa el nombre que mandó el cliente.
        $ruta = Storage::disk('public')->putFile('nosotros', $request->file('imagen'));

        $pagina->update(['pno_imagen' => $ruta, 'pno_usuid' => auth('api')->id()]);

        // Se borra la anterior recién después de guardar: si algo falla, no queda sin imagen.
        if ($anterior && $anterior !== $ruta) {
            Storage::disk('public')->delete($anterior);
        }

        return response()->json([
            'message' => 'Imagen actualizada.',
            'data'    => new PaginaNosotrosResource($pagina->fresh()),
        ]);
    }

    // DELETE /api/admin/pagina-nosotros/imagen
    public function eliminarImagen(): JsonResponse
    {
        $pagina = PaginaNosotros::actual();

        if ($pagina->pno_imagen) {
            Storage::disk('public')->delete($pagina->pno_imagen);
            $pagina->update(['pno_imagen' => null, 'pno_usuid' => auth('api')->id()]);
        }

        return response()->json([
            'message' => 'Imagen eliminada.',
            'data'    => new PaginaNosotrosResource($pagina->fresh()),
        ]);
    }
}