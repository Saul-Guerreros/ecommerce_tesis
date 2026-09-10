<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\StockResource;
use App\Models\Compras\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solo lectura. No tiene store/update/destroy: 'stock' se escribe
 * unicamente desde CompraService y AjusteService.
 */
class StockController extends Controller
{
    // GET /api/admin/stock?producto=&deposito=&marca=&bajo_minimo=
    public function index(Request $request): JsonResponse
    {
        $query = Stock::with(['producto', 'deposito'])
            ->whereHas('producto'); // descarta filas huerfanas si algo raro pasara

        if ($request->filled('producto')) {
            $query->where('sto_prdid', $request->producto);
        }

        if ($request->filled('deposito')) {
            $query->where('sto_depid', $request->deposito);
        }

        if ($request->filled('marca')) {
            $query->whereHas('producto', fn ($q) => $q->where('prd_marid', $request->marca));
        }

        $stock = $query->orderBy('sto_cantid')->get();

        if ($request->boolean('bajo_minimo')) {
            $stock = $stock->filter(fn ($s) =>
                $s->producto?->prd_stkmin !== null && (float) $s->sto_cantid < (float) $s->producto->prd_stkmin
            )->values();
        }

        return response()->json(['data' => StockResource::collection($stock)], 200);
    }

    // GET /api/admin/stock/config
    public function config(): JsonResponse
    {
        $productos = DB::table('producto')
            ->orderBy('prd_descri')
            ->get(['prd_id', 'prd_codinv', 'prd_descri']);

        $depositos = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->orderBy('dpo_nombre')
            ->get(['dpo_id', 'dpo_nombre', 'suc_nombre']);

        $marcas = DB::table('marca')
            ->where('mar_estado', 1)
            ->orderBy('mar_descri')
            ->get(['mar_id', 'mar_descri']);

        return response()->json([
            'productos' => $productos,
            'depositos' => $depositos,
            'marcas'    => $marcas,
        ], 200);
    }
}