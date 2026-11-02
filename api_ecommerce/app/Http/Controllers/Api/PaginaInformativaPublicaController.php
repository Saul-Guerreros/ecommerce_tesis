<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\PaginaInformativa;
use Illuminate\Http\JsonResponse;

class PaginaInformativaPublicaController extends Controller
{
    // GET /api/tienda/paginas/{slug}
    public function show(string $slug): JsonResponse
    {
        $pagina = PaginaInformativa::where('pgi_slug', $slug)->first(['pgi_titulo', 'pgi_contenido']);
        if (! $pagina) {
            return response()->json(['message' => 'Página no encontrada'], 404);
        }

        return response()->json(['data' => $pagina]);
    }
}