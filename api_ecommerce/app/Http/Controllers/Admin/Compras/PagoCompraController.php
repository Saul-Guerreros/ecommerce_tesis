<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\PagoCompraRequest;
use App\Http\Resources\Compra\PagoCompraResource;
use App\Models\Compras\PagoCompra;
use App\Services\Compras\CompraService;
use Illuminate\Http\JsonResponse;

use App\Http\Requests\Compra\AnularPagoCompraRequest;

class PagoCompraController extends Controller
{
    public function __construct(private CompraService $service)
    {
    }

    // POST /api/admin/pagos-compra
    public function store(PagoCompraRequest $request): JsonResponse
    {
        $pago = $this->service->registrarPago($request->validated());

        return response()->json([
            'message' => 'Pago registrado. La cuota quedó marcada como pagada.',
            'data'    => new PagoCompraResource($pago),
        ], 201);
    }

    // PATCH /api/admin/pagos-compra/{id}/anular
    public function anular(AnularPagoCompraRequest $request, int $id): JsonResponse
    {
        $pago = PagoCompra::find($id);

        if (!$pago) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        try {
            $pago = $this->service->anularPago($pago, $request->validated('pgo_motanu'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Pago anulado. La cuota vuelve a quedar pendiente.',
            'data'    => new PagoCompraResource($pago),
        ], 200);
    }
}