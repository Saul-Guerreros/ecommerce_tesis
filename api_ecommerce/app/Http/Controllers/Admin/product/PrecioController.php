<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Requests\product\PrecioRequest;
use App\Http\Resources\product\PrecioResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrecioController extends Controller
{
    /**
     * Costo promedio ponderado GLOBAL (suma en todos los depositos) de un
     * producto. Misma formula que ProductoController::conStock(), pero
     * para uno solo: SUM(cantidad x costo) / SUM(cantidad).
     * Devuelve null si el producto todavia no tiene stock (nunca se compro).
     */
    private function costoPromedioGlobal(int $prdId): ?float
    {
        $fila = DB::table('stock')
            ->select(
                DB::raw('SUM(sto_cantid) AS cantidad_total'),
                DB::raw('SUM(sto_cantid * sto_cosprm) AS costo_total')
            )
            ->where('sto_prdid', $prdId)
            ->first();

        $cantidad = $fila ? (float) $fila->cantidad_total : 0;

        return $cantidad > 0 ? ((float) $fila->costo_total) / $cantidad : null;
    }

    // GET /api/admin/precios?search=
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()->with('tipoIva')->where('prd_estado', 1);   // <-- with('tipoIva') agregado
        $query = Producto::query()->where('prd_estado', 1);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(prd_descri) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(prd_codinv) LIKE ?', [$search]);
            });
        }

        $productos = $query->orderBy('prd_descri')->get();

        $agregados = DB::table('stock')
            ->select(
                'sto_prdid',
                DB::raw('SUM(sto_cantid) AS cantidad_total'),
                DB::raw('SUM(sto_cantid * sto_cosprm) AS costo_total')
            )
            ->whereIn('sto_prdid', $productos->pluck('prd_id'))
            ->groupBy('sto_prdid')
            ->get()
            ->keyBy('sto_prdid');

        $productos->each(function ($p) use ($agregados) {
            $fila = $agregados->get($p->prd_id);
            $cantidad = $fila ? (float) $fila->cantidad_total : 0;
            $p->costo_promedio = $cantidad > 0 ? round(((float) $fila->costo_total) / $cantidad, 4) : null;
            $p->tiv_tasa = $p->tipoIva?->tiv_tasa;   // <-- NUEVO
        });

        return response()->json(['data' => PrecioResource::collection($productos)], 200);
    }

    // PATCH /api/admin/precios/{id}
   public function update(PrecioRequest $request, int $id): JsonResponse
{
    $producto = Producto::with('tipoIva')->find($id);

    if (!$producto) {
        return response()->json(['message' => 'Producto no encontrado'], 404);
    }

    $margen = $request->validated()['prd_margen'];
    $costoPromedio = $this->costoPromedioGlobal($id);

    $producto->prd_margen = $margen;

    if ($costoPromedio !== null && $costoPromedio > 0) {
        $tasaIva      = (float) ($producto->tipoIva->tiv_tasa ?? 0);
        $precioSinIva = $costoPromedio * (1 + $margen / 100);
        $producto->prd_prevta = round($precioSinIva * (1 + $tasaIva / 100), 2);
    }

    $producto->save();
    $producto->costo_promedio = $costoPromedio !== null ? round($costoPromedio, 4) : null;

    return response()->json([
        'message' => 'Precio actualizado correctamente.',
        'data'    => new PrecioResource($producto),
    ], 200);
}
}