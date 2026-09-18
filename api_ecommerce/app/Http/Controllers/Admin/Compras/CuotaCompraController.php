<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\CuotaCompraResource;
use App\Models\Compras\CuotaCompra;
use App\Services\Compras\CompraService;
use Illuminate\Http\JsonResponse;

class CuotaCompraController extends Controller
{
    public function __construct(private CompraService $service){
    
    }

    // GET /api/admin/compras/{id}/cuotas
    public function index(int $comId): JsonResponse
    {
        $cuotas = CuotaCompra::with('pagos')->where('cuo_comid', $comId)->orderBy('cuo_nrocuo')->get();
        return response()->json(['data' => CuotaCompraResource::collection($cuotas)], 200);
    }

    // POST /api/admin/cuotas-compra/{id}/pagos
    public function pagar(PagoCompraRequest $request, int $id): JsonResponse
    {
        $cuota = CuotaCompra::find($id);

        if (!$cuota) {
            return response()->json(['message' => 'Cuota no encontrada'], 404);
        }

        try {
            $cuota = $this->service->registrarPago($cuota, $request->validated());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data'    => new CuotaCompraResource($cuota),
        ], 201); // 201: se creo un pago_compra, no solo se actualizo un estado
    }
    
}