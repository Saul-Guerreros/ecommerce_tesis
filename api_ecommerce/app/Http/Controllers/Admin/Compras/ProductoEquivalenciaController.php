<?php
// app/Http/Controllers/Admin/Compras/ProductoEquivalenciaController.php

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
    /** tabla => [columna que referencia a peq_id, etiqueta para el mensaje] */
    private const DEPENDENCIAS = [
        'compra_detalle' => ['cmd_peqid', 'líneas de compra'],
    ];

    // GET /api/admin/producto-equivalencias?producto=<prd_id>  (obligatorio)
    // Devuelve las propias + las heredadas del modelo base (flag "heredada").
    public function index(Request $request): JsonResponse
    {
        $request->validate(['producto' => ['required', 'integer', 'exists:producto,prd_id']]);

        $equivalencias = ProductoEquivalencia::with('unidadMedida')
            ->withCount('detallesCompra')
            ->disponiblesPara((int) $request->producto)
            // Primero las heredadas del modelo base, después las propias.
            ->orderByRaw('CASE WHEN peq_prdid = ? THEN 1 ELSE 0 END', [(int) $request->producto])
            ->orderBy('peq_umdid')
            ->orderBy('peq_factor')
            ->get();

        return response()->json(['data' => ProductoEquivalenciaResource::collection($equivalencias)], 200);
    }

    // POST /api/admin/producto-equivalencias
    public function store(ProductoEquivalenciaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['peq_estado'] = $datos['peq_estado'] ?? 1;

        $equivalencia = ProductoEquivalencia::create($datos);
        $equivalencia->load('unidadMedida')->loadCount('detallesCompra');

        return response()->json([
            'message' => 'Equivalencia registrada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia),
        ], 201);
    }

    /**
     * PUT /api/admin/producto-equivalencias/{id}
     * Solo se edita el FACTOR, y únicamente si la presentación nunca se usó en una compra.
     * Producto + unidad son su identidad: no se reasignan.
     */
    public function update(ProductoEquivalenciaRequest $request, int $id): JsonResponse
    {
        $equivalencia = ProductoEquivalencia::find($id);
        if (!$equivalencia) return response()->json(['message' => 'Equivalencia no encontrada'], 404);

        if ((int) $request->peq_prdid !== $equivalencia->peq_prdid
            || (int) $request->peq_umdid !== $equivalencia->peq_umdid) {
            return response()->json(['errors' => [
                'peq_umdid' => ['No se puede cambiar el producto ni la unidad de una equivalencia existente. Cree una nueva.'],
            ]], 422);
        }

        $cambiaFactor = round((float) $request->peq_factor, 4) !== round((float) $equivalencia->peq_factor, 4);

        if ($cambiaFactor && $this->contarUsos($id)) {
            return response()->json([
                'message' => 'No se puede modificar la equivalencia: ya fue usada en compras registradas. Cree una nueva y desactive esta.',
            ], 409);
        }

        $equivalencia->update(['peq_factor' => $request->peq_factor]);
        $equivalencia->load('unidadMedida')->loadCount('detallesCompra');

        return response()->json([
            'message' => 'Equivalencia actualizada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia),
        ], 200);
    }

    // DELETE /api/admin/producto-equivalencias/{id}
    public function destroy(int $id): JsonResponse
    {
        $equivalencia = ProductoEquivalencia::find($id);
        if (!$equivalencia) return response()->json(['message' => 'Equivalencia no encontrada'], 404);

        $usos = $this->contarUsos($id);
        if ($usos) {
            return response()->json([
                'message' => 'No se puede eliminar: ya se usó en ' . $this->detalleUsos($usos) . '. Desactívela en su lugar.',
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
        $equivalencia->load('unidadMedida')->loadCount('detallesCompra');

        return response()->json([
            'message' => $equivalencia->peq_estado == 1 ? 'Equivalencia activada correctamente' : 'Equivalencia desactivada correctamente',
            'data'    => new ProductoEquivalenciaResource($equivalencia),
        ], 200);
    }

    // ------------------------------------------------------------------

    /** @return array<string,int> etiqueta => cantidad de usos (vacío si no se usó) */
    private function contarUsos(int $id): array
    {
        $usos = [];
        foreach (self::DEPENDENCIAS as $tabla => [$columna, $etiqueta]) {
            $n = DB::table($tabla)->where($columna, $id)->count();
            if ($n > 0) $usos[$etiqueta] = $n;
        }
        return $usos;
    }

    private function detalleUsos(array $usos): string
    {
        return implode(', ', array_map(fn ($etiqueta, $n) => "$n $etiqueta", array_keys($usos), $usos));
    }
}