<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\CuotaCompraResource;
use App\Models\Compras\CuotaCompra;
use Illuminate\Http\JsonResponse;

class CuotaCompraController extends Controller
{
    // GET /api/admin/compras/{id}/cuotas
    public function index(int $comId): JsonResponse
    {
        $cuotas = CuotaCompra::with('pago')
            ->where('cuo_comid', $comId)
            ->orderBy('cuo_nrocuo')
            ->get();

        return response()->json(['data' => CuotaCompraResource::collection($cuotas)], 200);
    }
}