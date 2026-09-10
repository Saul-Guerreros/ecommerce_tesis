<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\MovimientoStockResource;
use App\Models\Compras\MovimientoStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solo lectura (Kardex). No tiene store/update/destroy: el unico camino de
 * escritura hacia movimiento_stock es CompraService y AjusteService.
 */
class MovimientoStockController extends Controller
{
    // GET /api/admin/movimientos-stock?producto=&deposito=&tipo=&desde=&hasta=
    public function index(Request $request): JsonResponse
    {
        $query = MovimientoStock::with(['producto', 'deposito']);

        if ($request->filled('producto')) {
            $query->where('mst_stprid', $request->producto);
        }

        if ($request->filled('deposito')) {
            $query->where('mst_stdeid', $request->deposito);
        }

        if ($request->filled('tipo')) {
            $query->where('mst_tipo', $request->tipo);
        }

        if ($request->filled('desde')) {
            $query->whereDate('mst_fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('mst_fecha', '<=', $request->hasta);
        }

        $movimientos = $query->orderByDesc('mst_id')->limit(500)->get();

        return response()->json(['data' => MovimientoStockResource::collection($movimientos)], 200);
    }

    // GET /api/admin/movimientos-stock/config
    public function config(): JsonResponse
    {
        $productos = DB::table('producto')
            ->orderBy('prd_descri')
            ->get(['prd_id', 'prd_codinv', 'prd_descri']);

        $depositos = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->orderBy('dpo_nombre')
            ->get(['dpo_id', 'dpo_nombre', 'suc_nombre']);

        return response()->json([
            'productos' => $productos,
            'depositos' => $depositos,
        ], 200);
    }
}