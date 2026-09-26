<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\NotaCreditoRequest;
use App\Http\Resources\Compra\{MotivoNotaCreditoResource, NotaCreditoResource};
use App\Models\Compras\{CompraCab, DetNotaCredito, MotivoNotaCredito, NotaCredito, TimbradoProveedor, TipoComprobante};
use App\Services\Compras\NotaCreditoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        // Stock por producto en el deposito de la compra (una sola consulta).
        $stocks = DB::table('stock')
            ->where('sto_depid', $compra->com_depid)
            ->whereIn('sto_prdid', $compra->detalle->pluck('cmd_prdid'))
            ->get()
            ->keyBy('sto_prdid');

        $lineas = $compra->detalle->map(function ($d) use ($stocks) {
            $acreditado = DetNotaCredito::where('dnc_cmdid', $d->cmd_id)->sum('dnc_cantidad');
            $factor     = max((float) $d->cmd_factconv, 0.0001);
            $stock      = $stocks->get($d->cmd_prdid);

            return [
                'cmd_id'            => $d->cmd_id,
                'prd_codinv'        => $d->producto->prd_codinv ?? null,
                'cmd_descri'        => $d->cmd_descri,
                'cmd_tasa'          => $d->cmd_tasa,
                'cmd_canfac'        => $d->cmd_canfac,
                'cmd_precun'        => $d->cmd_precun,
                // Precio neto de descuento, por unidad de STOCK, IVA incluido.
                'precio_neto'       => round(((float) $d->cmd_precun - (float) $d->cmd_descto) / $factor, 4),
                'saldo_acreditable' => round((float) $d->cmd_canfac - (float) $acreditado, 4),
                'stock_disponible'  => $stock ? round((float) $stock->sto_cantid - (float) $stock->sto_reserv, 4) : 0,
            ];
        });

        $tipoNotaCredito = DB::table('tipo_comprobante')
            ->where('tco_codigo', TipoComprobante::CODIGO_NOTA_CREDITO)
            ->where('tco_estado', 1)
            ->first(['tco_id', 'tco_descri']);

        return response()->json([
            'com_id'            => $compra->com_id,
            'com_proid'         => $compra->com_proid,
            'com_nrodoc'        => $compra->com_nrodoc,
            'com_proraz'        => $compra->com_proraz,
            'com_proruc'        => $compra->com_proruc,
            'com_fchaem'        => $compra->com_fchaem?->format('Y-m-d'),
            'tipo_nota_credito' => $tipoNotaCredito,
            'motivos'           => MotivoNotaCreditoResource::collection(
                MotivoNotaCredito::activos()->orderBy('mnc_id')->get()
            ),
            'lineas'            => $lineas,
        ], 200);
    }

    // GET /api/admin/compras/{id}/notas-credito
    public function index(int $comId): JsonResponse
    {
        $notas = NotaCredito::with(['motivo', 'detalle.compraDetalle.producto'])
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

        $timbrado = !empty($datos['ncr_tpvid']) ? TimbradoProveedor::find($datos['ncr_tpvid']) : null;
        $datos['ncr_timbra'] = $timbrado?->tpv_numero;
        $datos['ncr_timbvn'] = $timbrado?->tpv_fechfn?->toDateString();

        try {
            $nota = $this->service->store($datos, $lineas);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $afectaStock = $nota->motivo?->afectaStock() ?? false;

        return response()->json([
            'message' => $afectaStock
                ? 'Nota de Crédito registrada. Stock descontado del depósito de la compra.'
                : 'Nota de Crédito registrada. Costo promedio del stock disponible actualizado.',
            'data'    => new NotaCreditoResource($nota),
        ], 201);
    }
}