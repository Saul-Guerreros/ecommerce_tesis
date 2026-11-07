<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Configuracion\DatosContactoResource;
use App\Http\Resources\Configuracion\PaginaNosotrosResource;
use App\Models\Configuracion\DatosContacto;
use App\Models\Configuracion\PaginaNosotros;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Páginas institucionales públicas de la tienda.
 * Se cachean 1 hora; el modelo invalida la caché al guardarse, así que
 * un cambio en el admin se ve en la tienda en el siguiente request.
 */
class PaginaInstitucionalController extends Controller
{
    // GET /api/tienda/nosotros
    public function nosotros(): JsonResponse
    {
        $data = Cache::remember(PaginaNosotros::CACHE_PUBLICA, now()->addHour(), function () {
            $pagina = PaginaNosotros::query()->first();
            return ($pagina && $pagina->pno_publicado)
                ? (new PaginaNosotrosResource($pagina))->resolve()
                : null; // null no se cachea: al publicar se ve enseguida
        });

        return $data
            ? response()->json(['data' => $data])
            : response()->json(['message' => 'Página no disponible'], 404);
    }

    // GET /api/tienda/contacto
    public function contacto(): JsonResponse
    {
        $data = Cache::remember(DatosContacto::CACHE_PUBLICA, now()->addHour(), function () {
            $contacto = DatosContacto::query()->first();
            return ($contacto && $contacto->dco_publicado)
                ? (new DatosContactoResource($contacto))->resolve()
                : null;
        });

        return $data
            ? response()->json(['data' => $data])
            : response()->json(['message' => 'Página no disponible'], 404);
    }
}