<?php

namespace App\Http\Controllers\Credito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\DeclararPagoCuotaRequest;
use App\Http\Resources\Pedido\MetodoPagoResource;
use App\Http\Resources\Venta\MiCuotaResource;
use App\Http\Resources\Venta\ResultadoPagoCuotaResource;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use App\Models\Venta\CtaCobrar;
use App\Models\Venta\CuotaPago;
use App\Models\Venta\CuotaPagoMedio;
use App\Models\Venta\VentaCab;
use App\Services\Credito\ComposicionCuotaService;
use App\Services\Venta\CuotaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * "Mis Cuotas": seccion propia del cliente.
 *
 * Lista sus compras a credito, muestra el cronograma de una de ellas y
 * recibe la declaracion de pago, con el mismo flujo del Paso 3 del
 * checkout: pago unico o dividido, y pantalla de resultado segun los
 * metodos elegidos.
 *
 * Declarar NO salda nada: la cuota pasa a COBRADA cuando el admin
 * registra el cobro desde Cuentas por Cobrar.
 */
class MisCuotasController extends Controller
{
    public function __construct(
        private CuotaPagoService $service,
        private ComposicionCuotaService $composicion,
    ) {
    }

    /** GET /api/tienda/cuotas */
    public function listar(Request $request): JsonResponse
    {
        $cliId = $this->clienteId($request);

        if (!$cliId) {
            return response()->json(['data' => []], 200);
        }

        $facturas = VentaCab::with(['pedido', 'cuotas'])
            ->where('ven_cliid', $cliId)
            ->vigente()
            ->whereHas('cuotas')
            ->orderByDesc('ven_id')
            ->get();

        $data = $facturas
            ->filter(fn ($f) => $f->pedido !== null)
            ->map(function (VentaCab $factura) {
                $cuotas     = $factura->cuotas;
                $pendientes = $cuotas->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE);

                return [
                    'ped_numero'     => $factura->pedido->ped_numero,
                    'factura_numero' => $factura->ven_nrodoc,
                    'factura_fecha'  => $factura->ven_fchaem?->format('Y-m-d'),
                    'total'          => round((float) $factura->ven_total, 2),
                    'total_cuotas'   => $cuotas->count(),
                    'cobradas'       => $cuotas->where('cco_estado', CtaCobrar::ESTADO_COBRADA)->count(),
                    'saldo'          => round((float) $pendientes->sum('cco_saldo'), 2),
                    'proximo_venc'   => $pendientes->min('cco_vencim')?->format('Y-m-d'),
                    'vencidas'       => $pendientes->filter(fn ($c) => $c->estaVencida())->count(),
                ];
            })
            ->values();

        return response()->json(['data' => $data], 200);
    }

    /** GET /api/tienda/cuotas/pedido/{numero} */
    public function index(Request $request, string $numero): JsonResponse
    {
        $pedido = $this->pedidoDeCredito($request, $numero);

        if ($pedido instanceof JsonResponse) {
            return $pedido;
        }

        $contexto = $this->contextoPedido($pedido);
        $factura  = $pedido->factura()->with('fe')->first();

        if (!$factura) {
            return response()->json($contexto + [
                'facturado' => false,
                'factura'   => null,
                'cuotas'    => [],
                'resumen'   => null,
            ], 200);
        }

        $cuotas = CtaCobrar::with([
            'pagoUltimo.estadoPago',
            'pagoUltimo.medios.metodoPago',
            'pagoUltimo.medios.estadoPago',
            'pagoUltimo.medios.motivoRechazo',
            'pagoUltimo.medios.comprobante',
        ])
            ->where('cco_venid', $factura->ven_id)
            ->orderBy('cco_nrocuo')
            ->get();

        $desglose = $this->composicion->porCuota($pedido->solicitudCredito);

        $cuotas->each(fn (CtaCobrar $c) => $c->setAttribute(
            'productos_cuota',
            $desglose[(int) $c->cco_nrocuo] ?? []
        ));

        $pendientes = $cuotas->where('cco_estado', CtaCobrar::ESTADO_PENDIENTE);

        return response()->json($contexto + [
            'facturado' => true,
            'factura' => [
                'numero'         => $factura->ven_nrodoc,
                'fecha'          => $factura->ven_fchaem?->format('Y-m-d'),
                'total'          => round((float) $factura->ven_total, 2),
                'pdf_disponible' => $this->rutaPdfFactura($factura) !== null,
            ],
            'cuotas'  => MiCuotaResource::collection($cuotas),
            'resumen' => [
                'total_cuotas' => $cuotas->count(),
                'cobradas'     => $cuotas->where('cco_estado', CtaCobrar::ESTADO_COBRADA)->count(),
                'saldo'        => round((float) $pendientes->sum('cco_saldo'), 2),
                'proximo_venc' => $pendientes->min('cco_vencim')?->format('Y-m-d'),
            ],
        ], 200);
    }

    /**
     * GET /api/tienda/cuotas/pedido/{numero}/pago?cuotas=12,13
     *
     * Pantalla de pago. Devuelve 422 si las cuotas ya no son pagables:
     * la pantalla usa esa respuesta para volver al detalle en vez de
     * mostrar un formulario que siempre fallaria.
     */
    public function configPago(Request $request, string $numero): JsonResponse
    {
        $pedido = $this->pedidoDeCredito($request, $numero);

        if ($pedido instanceof JsonResponse) {
            return $pedido;
        }

        $ids    = $this->idsDeCuotas($request);
        $cuotas = $this->service->cuotasPagables($ids);

        // Pertenencia: las cuotas tienen que ser de la factura de ESTE
        // pedido. Sin esto, un id ajeno en la query pagaria otra deuda.
        $factura = $pedido->factura()->first();

        if (!$factura || $cuotas->first()->cco_venid !== $factura->ven_id) {
            return response()->json(['message' => 'Cuota no encontrada'], 404);
        }

        $datos    = $this->service->datosDePago();
        $sucursal = $pedido->entrega?->sucursal ?? $pedido->sucursal;

        return response()->json([
            'pedido'  => ['numero' => $pedido->ped_numero],
            'factura' => ['numero' => $factura->ven_nrodoc],

            'cuotas' => $cuotas->map(fn (CtaCobrar $c) => [
                'cco_id'      => (int) $c->cco_id,
                'numero'      => (int) $c->cco_nrocuo,
                'vencimiento' => $c->cco_vencim?->format('Y-m-d'),
                'importe'     => round((float) $c->cco_monto, 2),
            ])->values(),

            'total' => (int) round((float) $cuotas->sum('cco_monto')),

            'metodos'             => MetodoPagoResource::collection($datos['metodos']),
            'datos_transferencia' => $datos['datos_transferencia'],
            'datos_billetera'     => $datos['datos_billetera'],
            'mensaje_efectivo'    => $datos['mensaje_efectivo'],
            'datos_cheque'        => $datos['datos_cheque'],
            'cheque_habilitado'   => $datos['cheque_habilitado'],
            'cheque_motivo'       => $datos['cheque_motivo'],

            'sucursal' => $sucursal ? [
                'nombre'    => $sucursal->suc_nombre,
                'direccion' => $sucursal->suc_direccion,
                'horario'   => $sucursal->suc_horario,
            ] : null,
        ], 200);
    }

    /** POST /api/tienda/cuotas/pagar */
    public function pagar(DeclararPagoCuotaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // Guarda de pertenencia: todas tienen que ser del cliente.
        foreach ($datos['cuotas'] as $id) {
            if (!$this->cuotaDelCliente($request, (int) $id)) {
                return response()->json(['message' => 'Cuota no encontrada'], 404);
            }
        }

        $pago = $this->service->declarar($datos['cuotas'], $datos);

        return response()->json([
            'message' => 'Recibimos tu pago. Lo vamos a verificar y te avisamos.',
            'data'    => $this->resultado($pago),
        ], 201);
    }

    /**
     * GET /api/tienda/cuotas/pagos/{pago}/resultado
     *
     * Pantalla final. Vive en su propia ruta y se resuelve por el id de
     * la declaracion, asi que sigue siendo valida de forma permanente: el
     * cliente puede recargarla o volver con el boton del navegador.
     */
    public function resultadoPago(Request $request, int $pago): JsonResponse
    {
        $registro = CuotaPago::with([
            'detalle.cuota', 'medios.metodoPago', 'medios.estadoPago',
            'medios.comprobante', 'medios.cheque',
        ])->find($pago);

        if (!$registro || !$this->pagoDelCliente($request, $registro)) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        return response()->json(['data' => $this->resultado($registro)], 200);
    }

    /** POST /api/tienda/cuotas/medios/{medio}/comprobante */
    public function reemplazarComprobante(Request $request, int $medio): JsonResponse
    {
        $request->validate([
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $registro = $this->medioDelCliente($request, $medio);

        if (!$registro) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        $this->service->reemplazarComprobante($registro, $request->file('comprobante'));

        return response()->json([
            'message' => 'Comprobante enviado. Lo vamos a verificar.',
        ], 200);
    }

    /** GET /api/tienda/cuotas/medios/{medio}/comprobante */
    public function verComprobante(Request $request, int $medio)
    {
        $registro = $this->medioDelCliente($request, $medio);
        $archivo  = $registro?->comprobante?->ccm_archiv;

        if (!$archivo || !Storage::disk('local')->exists($archivo)) {
            abort(404, 'Comprobante no disponible');
        }

        return Storage::disk('local')->response($archivo);
    }

    /** GET /api/tienda/cuotas/pedido/{numero}/factura */
    public function factura(Request $request, string $numero)
    {
        $pedido = $this->pedidoDelCliente($request, $numero);

        if (!$pedido) {
            abort(404, 'Pedido no encontrado');
        }

        $factura = $pedido->factura()->with('fe')->first();
        $ruta    = $factura ? $this->rutaPdfFactura($factura) : null;

        if (!$ruta) {
            abort(404, 'La factura todavía no está disponible.');
        }

        return Storage::disk('local')->response(
            $ruta,
            "{$factura->ven_nrodoc}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    // ---------- Armado ----------

    /**
     * El resultado necesita el numero de pedido y la sucursal, que no
     * salen de cuota_pago: se resuelven desde la factura de la primera
     * cuota (todas son de la misma, lo garantiza el service).
     */
    private function resultado(CuotaPago $pago): array
    {
        $pago->loadMissing('detalle.cuota.factura.pedido.entrega.sucursal', 'detalle.cuota.factura.pedido.sucursal');

        $pedido   = $pago->detalle->first()?->cuota?->factura?->pedido;
        $sucursal = $pedido?->entrega?->sucursal ?? $pedido?->sucursal;

        $recurso = new ResultadoPagoCuotaResource(
            $pago,
            (string) ($pedido?->ped_numero ?? ''),
            $sucursal ? [
                'nombre'    => $sucursal->suc_nombre,
                'direccion' => $sucursal->suc_direccion,
                'horario'   => $sucursal->suc_horario,
            ] : null,
        );

        return $recurso->resolve();
    }

    private function contextoPedido(Pedido $pedido): array
    {
        $pedido->loadMissing(['facturacion', 'tipoEntrega', 'solicitudCredito.detalles']);

        return [
            'pedido' => [
                'numero' => $pedido->ped_numero,
                'creado' => $pedido->ped_creado?->toIso8601String(),
            ],
            'resumen_compra' => [
                'subtotal'    => (float) $pedido->ped_subtot,
                'impuestos'   => (float) $pedido->ped_impues,
                'costo_envio' => (float) $pedido->ped_cosenv,
                'total'       => (float) $pedido->ped_total,
                'es_retiro'   => $pedido->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO,
            ],
            'facturacion' => $pedido->facturacion ? [
                'razon_social'   => $pedido->facturacion->pfa_raznom,
                'tipo_documento' => $pedido->facturacion->pfa_tipodoc,
                'nro_documento'  => $pedido->facturacion->pfa_nrodoc,
            ] : null,
        ];
    }

    /** Acepta cuotas=1,2 y cuotas[]=1&cuotas[]=2. */
    private function idsDeCuotas(Request $request): array
    {
        $crudo = $request->query('cuotas', []);

        if (is_string($crudo)) {
            $crudo = explode(',', $crudo);
        }

        return array_map('intval', array_filter((array) $crudo, fn ($v) => $v !== ''));
    }

    private function rutaPdfFactura(VentaCab $factura): ?string
    {
        $ruta = $factura->fe?->vfe_pdf_ruta;

        return $ruta && Storage::disk('local')->exists($ruta) ? $ruta : null;
    }

    // ---------- Guardas de pertenencia ----------

    private function clienteId(Request $request): ?int
    {
        $usuario = $request->user();
        $usuario?->loadMissing('persona.cliente');

        return $usuario?->persona?->cliente?->cli_id;
    }

    private function pedidoDelCliente(Request $request, string $numero): ?Pedido
    {
        $cliId = $this->clienteId($request);

        if (!$cliId) {
            return null;
        }

        return Pedido::with('entrega.sucursal', 'sucursal')
            ->where('ped_numero', $numero)
            ->where('ped_cliid', $cliId)
            ->first();
    }

    /** Pedido del cliente que además es a crédito, o la respuesta de error. */
    private function pedidoDeCredito(Request $request, string $numero): Pedido|JsonResponse
    {
        $pedido = $this->pedidoDelCliente($request, $numero);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        if (!$pedido->esDeCredito()) {
            return response()->json(['message' => 'Este pedido no es a crédito.'], 409);
        }

        return $pedido;
    }

    private function cuotaDelCliente(Request $request, int $ccoId): ?CtaCobrar
    {
        $cliId = $this->clienteId($request);

        if (!$cliId) {
            return null;
        }

        return CtaCobrar::where('cco_id', $ccoId)
            ->whereHas('factura', fn ($q) => $q->where('ven_cliid', $cliId))
            ->first();
    }

    private function medioDelCliente(Request $request, int $cpmId): ?CuotaPagoMedio
    {
        $medio = CuotaPagoMedio::with('metodoPago', 'estadoPago', 'comprobante', 'pago.detalle')
            ->find($cpmId);

        if (!$medio || !$medio->pago || !$this->pagoDelCliente($request, $medio->pago)) {
            return null;
        }

        return $medio;
    }

    /** La declaración es del cliente si todas sus cuotas lo son. */
    private function pagoDelCliente(Request $request, CuotaPago $pago): bool
    {
        $pago->loadMissing('detalle');

        foreach ($pago->detalle as $linea) {
            if (!$this->cuotaDelCliente($request, (int) $linea->cpd_ccoid)) {
                return false;
            }
        }

        return $pago->detalle->isNotEmpty();
    }
}