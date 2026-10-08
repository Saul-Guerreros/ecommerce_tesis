<?php

namespace App\Http\Controllers\Admin\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\CategoriaAtributoRequest;
use App\Http\Resources\Catalogo\CategoriaAtributoResource;
use App\Models\Catalogo\CategoriaAtributo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas de Ficha Técnica: qué atributos técnicos lleva cada
 * subcategoría. Ninguna tabla tiene FK hacia categoria_atributo
 * (especificacion_producto apunta a atributo, no a la plantilla), por eso
 * el borrado no se bloquea: quitar un atributo de la plantilla no borra
 * los valores ya cargados en las fichas de los productos.
 */
class CategoriaAtributoController extends Controller
{
    /**
     * GET /api/admin/categoria-atributos/categorias
     * Subcategorías (con su categoría padre) y cuántos atributos tiene
     * cada plantilla, para el panel izquierdo de la pantalla.
     */
    public function categorias(): JsonResponse
    {
        $categorias = DB::table('categoria as c')
            ->join('categoria as p', 'p.cat_id', '=', 'c.cat_idpadr')
            ->leftJoin('categoria_atributo as ca', 'ca.cta_catid', '=', 'c.cat_id')
            ->groupBy('c.cat_id', 'c.cat_nombre', 'p.cat_nombre')
            ->orderBy('p.cat_nombre')
            ->orderBy('c.cat_nombre')
            ->get([
                'c.cat_id',
                'c.cat_nombre',
                'p.cat_nombre as padre_nombre',
                DB::raw('COUNT(ca.cta_id) as total_atributos'),
                DB::raw('COALESCE(SUM(ca.cta_obliga), 0) as total_obligatorios'),
            ]);

        return response()->json(['data' => $categorias], 200);
    }

    /**
     * GET /api/admin/categoria-atributos/config
     * Atributos activos para el <select> del formulario.
     */
    public function config(): JsonResponse
    {
        $atributos = DB::table('atributo')
            ->where('atb_estado', 1)
            ->whereNull('atb_eliminado')
            ->orderBy('atb_nombre')
            ->get(['atb_id', 'atb_nombre', 'atb_tipo', 'atb_unidad']);

        return response()->json(['atributos' => $atributos], 200);
    }

    /**
     * GET /api/admin/categoria-atributos?categoria=ID
     * Plantilla de una subcategoría, en el orden en que se mostrará en la ficha.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(
            ['categoria' => ['required', 'integer', 'exists:categoria,cat_id']],
            ['categoria.required' => 'Debe indicar la subcategoría.', 'categoria.exists' => 'La subcategoría no existe.']
        );

        $plantilla = CategoriaAtributo::where('cta_catid', $request->categoria)
            // whereHas excluye atributos eliminados (SoftDeletes de Atributo).
            ->whereHas('atributo')
            ->with(['atributo' => fn ($q) => $q->withCount('propiedades')])
            ->orderBy('cta_orden')
            ->orderBy('cta_id')
            ->get();

        return response()->json(['data' => CategoriaAtributoResource::collection($plantilla)], 200);
    }

    // POST /api/admin/categoria-atributos
    public function store(CategoriaAtributoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // Sin orden explícito, el atributo se agrega al final de la plantilla.
        if (empty($datos['cta_orden'])) {
            $datos['cta_orden'] = (int) CategoriaAtributo::where('cta_catid', $datos['cta_catid'])
                ->max('cta_orden') + 1;
        }

        $item = CategoriaAtributo::create($datos);
        $item->load(['atributo' => fn ($q) => $q->withCount('propiedades')]);

        return response()->json([
            'message' => 'Atributo agregado a la plantilla',
            'data'    => new CategoriaAtributoResource($item),
        ], 201);
    }

    // PUT /api/admin/categoria-atributos/{id}
    public function update(CategoriaAtributoRequest $request, int $id): JsonResponse
    {
        $item = CategoriaAtributo::find($id);

        if (!$item) {
            return response()->json(['message' => 'El atributo no existe en la plantilla'], 404);
        }

        // La categoría de una fila de plantilla no se cambia (ver el Request).
        $datos = Arr::except($request->validated(), ['cta_catid']);

        if (empty($datos['cta_orden'])) {
            $datos['cta_orden'] = $item->cta_orden;
        }

        $item->update($datos);
        $item->load(['atributo' => fn ($q) => $q->withCount('propiedades')]);

        return response()->json([
            'message' => 'Plantilla actualizada',
            'data'    => new CategoriaAtributoResource($item),
        ], 200);
    }

    // DELETE /api/admin/categoria-atributos/{id}
    public function destroy(int $id): JsonResponse
    {
        $item = CategoriaAtributo::find($id);

        if (!$item) {
            return response()->json(['message' => 'El atributo no existe en la plantilla'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Atributo quitado de la plantilla'], 200);
    }
}
