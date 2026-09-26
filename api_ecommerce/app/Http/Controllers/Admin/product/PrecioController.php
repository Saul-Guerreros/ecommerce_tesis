<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Requests\product\PrecioRequest;
use App\Http\Resources\product\PrecioResource;
use App\Models\ConfiguracionPrecio;
use App\Models\Producto;
use App\Services\Precio\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrecioController extends Controller
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

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
        $query = Producto::query()->with('tipoIva')->where('prd_estado', 1);

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

        $general = ConfiguracionPrecio::actual();

        $productos->each(function ($p) use ($agregados, $general) {
            $fila     = $agregados->get($p->prd_id);
            $cantidad = $fila ? (float) $fila->cantidad_total : 0;

            $p->costo_promedio      = $cantidad > 0 ? round(((float) $fila->costo_total) / $cantidad, 4) : null;
            $p->tiv_tasa            = $p->tipoIva?->tiv_tasa;
            $p->margen_min_efectivo = (float) ($p->prd_marmin ?? $general->cpr_marmin);
            $p->margen_maj_efectivo = (float) ($p->prd_marmaj ?? $general->cpr_marmaj);
            $p->umbral_efectivo     = (float) ($p->prd_canminmaj ?? $general->cpr_canminmaj);
        });

        return response()->json(['data' => PrecioResource::collection($productos)], 200);
    }

    // GET /api/admin/precios/{id}
    // Precio de UN producto puntual (ej: para sugerir pub_prevta al crear
    // su publicación web). El listado de index() no sirve para esto: no
    // tiene forma práctica de pedir "solo este producto" sin depender de
    // que el texto de búsqueda matchee exacto.
    public function show(int $id): JsonResponse
    {
        $producto = Producto::with('tipoIva')->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $general       = ConfiguracionPrecio::actual();
        $costoPromedio = $this->costoPromedioGlobal($id);

        $producto->costo_promedio      = $costoPromedio !== null ? round($costoPromedio, 4) : null;
        $producto->tiv_tasa            = $producto->tipoIva?->tiv_tasa;
        $producto->margen_min_efectivo = (float) ($producto->prd_marmin ?? $general->cpr_marmin);
        $producto->margen_maj_efectivo = (float) ($producto->prd_marmaj ?? $general->cpr_marmaj);
        $producto->umbral_efectivo     = (float) ($producto->prd_canminmaj ?? $general->cpr_canminmaj);

        return response()->json(['data' => new PrecioResource($producto)], 200);
    }

    // PATCH /api/admin/precios/{id}
    public function update(PrecioRequest $request, int $id): JsonResponse
    {
        $producto = Producto::with('tipoIva')->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $datos = $request->validated();

        // null = "volver a usar el general": se guarda tal cual, nunca
        // se reemplaza por un valor inventado.
        $producto->prd_marmin    = $datos['prd_marmin']    ?? null;
        $producto->prd_marmaj    = $datos['prd_marmaj']    ?? null;
        $producto->prd_canminmaj = $datos['prd_canminmaj'] ?? null;
        $producto->save();

        $costoPromedio = $this->costoPromedioGlobal($id);
        $this->priceService->aplicarPrecios($producto, $costoPromedio);

        $general = ConfiguracionPrecio::actual();
        $producto->costo_promedio      = $costoPromedio !== null ? round($costoPromedio, 4) : null;
        $producto->tiv_tasa            = $producto->tipoIva?->tiv_tasa;
        $producto->margen_min_efectivo = (float) ($producto->prd_marmin ?? $general->cpr_marmin);
        $producto->margen_maj_efectivo = (float) ($producto->prd_marmaj ?? $general->cpr_marmaj);
        $producto->umbral_efectivo     = (float) ($producto->prd_canminmaj ?? $general->cpr_canminmaj);

        return response()->json([
            'message' => 'Precio actualizado correctamente.',
            'data'    => new PrecioResource($producto),
        ], 200);
    }
}