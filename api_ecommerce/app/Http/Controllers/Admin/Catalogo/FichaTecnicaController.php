<?php

namespace App\Http\Controllers\Admin\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalogo\FichaTecnicaCampoResource;
use App\Models\Producto;
use App\Services\Catalogo\FichaTecnicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solo LECTURA: arma los campos de la Ficha Técnica para el panel.
 * El guardado viaja junto con la Publicación Web (un solo botón, una
 * sola transacción), ver PublicacionWebController::guardar().
 */
class FichaTecnicaController extends Controller
{
    public function __construct(private FichaTecnicaService $fichaTecnica)
    {
    }

    /**
     * GET /api/admin/productos/{producto}/ficha-tecnica?categoria=ID
     * La categoría llega por query (no se lee de la publicación) porque el
     * admin puede estar cambiándola en el formulario antes de guardar.
     */
    public function show(Request $request, Producto $producto): JsonResponse
    {
        $request->validate(
            ['categoria' => ['required', 'integer', 'exists:categoria,cat_id']],
            [
                'categoria.required' => 'Debe indicar la subcategoría.',
                'categoria.exists'   => 'La subcategoría no existe.',
            ]
        );

        $esRaiz = DB::table('categoria')
            ->where('cat_id', $request->categoria)
            ->whereNull('cat_idpadr')
            ->exists();

        if ($esRaiz) {
            return response()->json([
                'message' => 'Elegí una subcategoría: la ficha técnica se define por subcategoría.',
                'errors'  => ['categoria' => ['Elegí una subcategoría.']],
            ], 422);
        }

        // En variantes, los campos traen lo heredado del producto base (solo lectura).
        $campos = $this->fichaTecnica->campos((int) $request->categoria, $producto);

        return response()->json(['data' => FichaTecnicaCampoResource::collection($campos)], 200);
    }
}
