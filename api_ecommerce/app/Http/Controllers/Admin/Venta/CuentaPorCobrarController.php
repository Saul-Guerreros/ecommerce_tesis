<?php

namespace App\Http\Controllers\Admin\Venta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\AnularCobroCuotaRequest;
use App\Http\Requests\Venta\CobroCuotaRequest;
use App\Http\Resources\Venta\CtaCobrarResource;
use App\Http\Resources\Venta\CuentaPorCobrarResource;
use App\Models\Venta\Recibo;
use App\Models\Venta\CtaCobrar;
use App\Models\Venta\VentaCab;
use App\Services\Venta\CtaCobrarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cartera de ventas a credito. Espejo de CuentaPorPagarController.
 */
class CuentaPorCobrarController extends Controller
{
    public function __construct(private CtaCobrarService $service)
    {
    }

    /**
     * GET /api/admin/cuentas-por-cobrar?search=&cliente=&estado=&desde=&hasta=
     *
     * estado: pendientes (default) | vencidas | todas
     */
    public function index(Request $request): JsonResponse
    {
        $pendiente = fn ($q) => $q->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE);

        $query = VentaCab::query()
            ->where('ven_condicion', VentaCab::CONDICION_CREDITO)
            ->vigente()
            ->withCount(['cuotas as cco_total'])
            ->withCount(['cuotas as cco_pendientes' => $pendiente])
            ->withSum(['cuotas as cco_saldo' => $pendiente], 'cco_saldo')
            ->withMin(['cuotas as cco_proxvencim' => $pendiente], 'cco_vencim');

        if ($request->filled('cliente')) {
            $query->where('ven_cliid', $request->cliente);
        }

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ven_cli_razon) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(ven_cli_doc) LIKE ?', [$search])
                  ->orWhereRaw("LOWER(ven_establec || '-' || ven_puntoexp || '-' || ven_numero) LIKE ?", [$search]);
            });
        }

        $facturas = $query->get();

        $estado = $request->input('estado', 'pendientes');
        $hoy    = now()->toDateString();

        if ($estado === 'vencidas') {
            $facturas = $facturas->filter(
                fn ($f) => $f->cco_proxvencim !== null && $f->cco_proxvencim < $hoy
            );
        } elseif ($estado !== 'todas') {
            $facturas = $facturas->filter(fn ($f) => (float) $f->cco_saldo > 0);
        }

        // Rango sobre el proximo vencimiento: "que me vence esta semana".
        if ($request->filled('desde')) {
            $facturas = $facturas->filter(
                fn ($f) => $f->cco_proxvencim !== null && $f->cco_proxvencim >= $request->desde
            );
        }

        if ($request->filled('hasta')) {
            $facturas = $facturas->filter(
                fn ($f) => $f->cco_proxvencim !== null && $f->cco_proxvencim <= $request->hasta
            );
        }

        // Sin saldo primero las que ya no vencen nada, al final de la lista.
        $facturas = $facturas
            ->sortBy(fn ($f) => $f->cco_proxvencim ?? '9999-12-31')
            ->values();

        return response()->json([
            'data' => CuentaPorCobrarResource::collection($facturas),
            'totales' => [
                'facturas' => $facturas->count(),
                'saldo'    => round((float) $facturas->sum('cco_saldo'), 2),
                'vencidas' => $facturas->filter(
                    fn ($f) => $f->cco_proxvencim !== null && $f->cco_proxvencim < $hoy
                )->count(),
            ],
        ], 200);
    }

    /**
     * GET /api/admin/cuentas-por-cobrar/config
     *
     * Clientes con al menos una venta a credito y metodos de pago para el
     * formulario de cobro.
     */
    public function config(): JsonResponse
    {
        $clientes = DB::table('venta_cab')
            ->where('ven_condicion', VentaCab::CONDICION_CREDITO)
            ->whereNotIn('ven_estado', [VentaCab::ESTADO_ANULADA])
            ->select('ven_cliid', 'ven_cli_razon')
            ->distinct()
            ->orderBy('ven_cli_razon')
            ->get();

        $metodos = DB::table('metodo_pago')
            ->where('mpa_estado', 1)
            ->orderBy('mpa_descri')
            ->get(['mpa_id', 'mpa_descri']);

        return response()->json([
            'clientes' => $clientes,
            'metodos'  => $metodos,
        ], 200);
    }

    /**
     * GET /api/admin/cuentas-por-cobrar/{venta}
     *
     * Cuotas de una factura, con el total adeudado por ese cliente en
     * TODAS sus facturas: quien atiende el mostrador necesita verlo sin
     * tener que sumar filas de la bandeja.
     */
    public function show(int $venta): JsonResponse
    {
                $factura = VentaCab::with(['cuotas.recibo.metodoPago', 'cliente'])->find($venta);

        if (!$factura) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        if (!$factura->esACredito()) {
            return response()->json(['message' => 'Esta factura no es a crédito.'], 409);
        }

        $deudaCliente = (float) CtaCobrar::where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)
            ->whereIn('cco_venid', VentaCab::where('ven_cliid', $factura->ven_cliid)
                ->vigente()
                ->select('ven_id'))
            ->sum('cco_saldo');

        return response()->json([
            'factura' => [
                'ven_id'        => (int) $factura->ven_id,
                'ven_nrodoc'    => $factura->ven_nrodoc,
                'ven_fchaem'    => $factura->ven_fchaem,
                'ven_total'     => round((float) $factura->ven_total, 2),
                'ven_cli_razon' => $factura->ven_cli_razon,
                'ven_cli_doc'   => $factura->ven_cli_doc,
                'ven_estado'    => (int) $factura->ven_estado,
                'cant_cuotas'   => (int) $factura->ven_cant_cuotas,
            ],
            'cuotas' => CtaCobrarResource::collection($factura->cuotas),
            'resumen' => [
                'saldo_factura' => round(
                    (float) $factura->cuotas->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE)->sum('cco_saldo'),
                    2
                ),
                'deuda_cliente' => round($deudaCliente, 2),
            ],
        ], 200);
    }

        /**
     * POST /api/admin/cuentas-por-cobrar/cobrar
     *
     * Cobra una o varias cuotas en un solo recibo. Las cuotas tienen que
     * ser de la misma factura y consecutivas desde la más antigua
     * pendiente: eso lo valida el service.
     */
    public function cobrar(CobroCuotaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $recibo = $this->service->registrarCobro(
            $datos['cuotas'],
            $datos,
            auth('api')->user(),
        );

        return response()->json([
            'message' => "Cobro registrado. Recibo {$recibo->rec_numero}.",
            'data'    => [
                'rec_id'     => (int) $recibo->rec_id,
                'rec_numero' => $recibo->rec_numero,
                'rec_monto'  => round((float) $recibo->rec_monto, 2),
                'cuotas'     => $recibo->detalle->pluck('rdt_ccoid'),
            ],
        ], 201);
    }

    /**
     * PATCH /api/admin/cuentas-por-cobrar/recibos/{recibo}/anular
     *
     * Anula el recibo entero: TODAS sus cuotas vuelven a Pendiente. No se
     * puede anular una cuota suelta de un recibo que cubre varias.
     */
    public function anularCobro(AnularCobroCuotaRequest $request, int $recibo): JsonResponse
    {
        $registro = Recibo::with('detalle')->find($recibo);

        if (!$registro) {
            return response()->json(['message' => 'Recibo no encontrado'], 404);
        }

        $anulado = $this->service->anularRecibo(
            $registro,
            $request->validated('rec_motanu'),
            auth('api')->user(),
        );

        return response()->json([
            'message' => "Recibo {$anulado->rec_numero} anulado. Las cuotas vuelven a quedar pendientes.",
            'data'    => ['rec_id' => (int) $anulado->rec_id],
        ], 200);
    }
}