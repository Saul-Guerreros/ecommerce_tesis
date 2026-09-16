<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\ProductoEquivalenciaRequest;
use App\Http\Resources\Compra\ProductoEquivalenciaResource;
use App\Models\Compras\ProductoEquivalencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoEquivalenciaController extends Controller
{
    // GET /api/admin/producto-equivalencias?producto=<prd_id>  (obligatorio)
    public function index(Request $request): JsonResponse
    {
        $request->validate(['producto' => ['required', 'integer', 'exists:producto,prd_id']]);

        $equivalencias = ProductoEquivalencia::with('unidadMedida')
            ->where('peq_prdid', $request->producto)
            ->orderBy('peq_id')
            ->get();

        return response()->json(['data' => ProductoEquivalenciaResource::collection($equivalencias)], 200);
    }

    // POST /api/admin/producto-equivalencias
    public function store(ProductoEquivalenciaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['peq_estado'] = $datos['peq_estado'] ?? 1;

        $equivalencia = ProductoEquivalencia::create($datos)->load('unidadMedida');

        return response()->json([
            'message' => 'Equivalencia registrada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia),
        ], 201);
    }

    // PUT /api/admin/producto-equivalencias/{id}
    public function update(ProductoEquivalenciaRequest $request, int $id): JsonResponse
    {
        $equivalencia = ProductoEquivalencia::find($id);
        if (!$equivalencia) return response()->json(['message' => 'Equivalencia no encontrada'], 404);

        $equivalencia->update($request->validated());

        return response()->json([
            'message' => 'Equivalencia actualizada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia->fresh('unidadMedida')),
        ], 200);
    }

    /**
     * DELETE /api/admin/producto-equivalencias/{id}
     * Bloqueada si ya fue usada en una compra (cmd_umdid, Fase B): el
     * factor queda "congelado" en cmd_factconv de esa línea, pero borrar
     * la fuente rompería la trazabilidad de por qué se usó ese valor.
     */
    public function destroy(int $id): JsonResponse
    {
        $equivalencia = ProductoEquivalencia::find($id);
        if (!$equivalencia) return response()->json(['message' => 'Equivalencia no encontrada'], 404);

        $enUso = DB::table('compra_detalle')
            ->where('cmd_prdid', $equivalencia->peq_prdid)
            ->where('cmd_umdid', $equivalencia->peq_umdid) // columna de la Fase B
            ->exists();

        if ($enUso) {
            return response()->json([
                'message' => 'No se puede eliminar: esta equivalencia ya fue usada en compras registradas. Desactívela en su lugar.',
            ], 409);
        }

        $equivalencia->delete();

        return response()->json(['message' => 'Equivalencia eliminada correctamente'], 200);
    }

    // PATCH /api/admin/producto-equivalencias/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $equivalencia = ProductoEquivalencia::find($id);
        if (!$equivalencia) return response()->json(['message' => 'Equivalencia no encontrada'], 404);

        $equivalencia->peq_estado = $equivalencia->peq_estado == 1 ? 0 : 1;
        $equivalencia->save();

        return response()->json([
            'message' => $equivalencia->peq_estado == 1 ? 'Equivalencia activada correctamente' : 'Equivalencia desactivada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia),
        ], 200);
    }
}