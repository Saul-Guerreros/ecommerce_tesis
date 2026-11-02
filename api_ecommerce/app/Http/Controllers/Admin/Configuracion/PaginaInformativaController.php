<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\PaginaInformativaRequest;
use App\Http\Resources\Configuracion\PaginaInformativaResource;
use App\Models\Configuracion\PaginaInformativa;
use Illuminate\Http\JsonResponse;

class PaginaInformativaController extends Controller
{
    // GET /api/admin/paginas-informativas
    public function index(): JsonResponse
    {
        $paginas = PaginaInformativa::all()
            ->sortBy(fn ($p) => array_search($p->pgi_slug, PaginaInformativa::ORDEN_SLUGS))
            ->values();

        return response()->json(['data' => PaginaInformativaResource::collection($paginas)]);
    }

    // GET /api/admin/paginas-informativas/{id}
    public function show(int $id): JsonResponse
    {
        $pagina = PaginaInformativa::find($id);
        if (! $pagina) {
            return response()->json(['message' => 'Página no encontrada'], 404);
        }

        return response()->json(['data' => new PaginaInformativaResource($pagina)]);
    }

    // PATCH /api/admin/paginas-informativas/{id}
    public function update(PaginaInformativaRequest $request, int $id): JsonResponse
    {
        $pagina = PaginaInformativa::find($id);
        if (! $pagina) {
            return response()->json(['message' => 'Página no encontrada'], 404);
        }

        $pagina->update($request->validated());

        return response()->json([
            'message' => 'Página actualizada correctamente',
            'data' => new PaginaInformativaResource($pagina->fresh()),
        ]);
    }
}