<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\NotaCreditoRequest;
use App\Http\Resources\Compra\NotaCreditoResource;
use App\Models\Compras\{CompraCab, DetNotaCredito, NotaCredito};
use App\Services\Compras\NotaCreditoService;
use Illuminate\Http\JsonResponse;

class NotaCreditoController extends Controller
{
    public function __construct(private NotaCreditoService $service)
    {
    }

    // GET /api/admin/compras/{id}/notas-credito/config
    public function config(int $comId): JsonResponse
    {
        $compra = CompraCab::with(['detalle.producto'])->find($comId);

        if (!$compra) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        $lineas = $compra->detalle->map(function ($d) {
            $acreditado = DetNotaCredito::where('dnc_cmdid', $d->cmd_id)->sum('dnc_cantidad');

            return [
                'cmd_id'            => $d->cmd_id,
                'prd_codinv'        => $d->producto->prd_codinv ?? null,
                'cmd_descri'        => $d->cmd_descri,
                'cmd_tasa'          => $d->cmd_tasa,
                'cmd_canfac'        => $d->cmd_canfac,
                'cmd_precun'        => $d->cmd_precun,
                'saldo_acreditable' => round((float) $d->cmd_canfac - (float) $acreditado, 4),
            ];
        });

        return response()->json([
            'com_id'     => $compra->com_id,
            'com_nrodoc' => $compra->com_nrodoc,
            'com_proraz' => $compra->com_proraz,
            'lineas'     => $lineas,
        ], 200);
    }

    // GET /api/admin/compras/{id}/notas-credito
    public function index(int $comId): JsonResponse
    {
        $notas = NotaCredito::with('detalle.compraDetalle.producto')
            ->where('ncr_comid', $comId)
            ->orderByDesc('ncr_id')
            ->get();

        return response()->json(['data' => NotaCreditoResource::collection($notas)], 200);
    }

    // POST /api/admin/compras/{id}/notas-credito
    public function store(NotaCreditoRequest $request, int $comId): JsonResponse
    {
        $datos = $request->safe()->except('detalle');
        $datos['ncr_comid'] = $comId;
        $lineas = $request->input('detalle');

        $nota = $this->service->store($datos, $lineas);

        return response()->json([
            'message' => 'Nota de Crédito registrada. Costo promedio del stock disponible actualizado.',
            'data'    => new NotaCreditoResource($nota),
        ], 201);
    }
}