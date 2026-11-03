<?php

namespace App\Http\Controllers\Admin\Venta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\NotaCreditoVentaRequest;
use App\Http\Resources\Compra\MotivoNotaCreditoResource;
use App\Http\Resources\Venta\NotaCreditoVentaResource;
use App\Models\Compras\MotivoNotaCredito;
use App\Models\Venta\NotaCreditoVenta;
use App\Models\Venta\VentaCab;
use App\Services\Venta\NotaCreditoVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Jobs\DeclararNotaCreditoElectronica;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Storage;

use App\Models\Venta\NotaCreditoVentaFe;

/**
 * Notas de Crédito de venta.
 *
 * Es un módulo propio (el listado completo vive acá) y además da servicio
 * al atajo que aparece dentro del detalle de la factura: por eso tiene dos
 * endpoints extra indexados por ven_id. Esas rutas no guardan nada
 * distinto; leen de la misma tabla filtrando por factura.
 *
 * No hay update(): un comprobante fiscal emitido no se edita. Se anula.
 */
class NotaCreditoVentaController extends Controller
{
        private const RELACIONES_LISTA   = ['motivo', 'factura', 'sucursal', 'fe'];
    private const RELACIONES_DETALLE = ['motivo', 'factura', 'sucursal', 'deposito', 'detalle', 'fe'];

    public function __construct(private NotaCreditoVentaService $service) {}

    // GET /api/admin/notas-credito-venta/config
    public function config(): JsonResponse
    {
        $sucursales = DB::table('sucursal')
            ->where('suc_estado', 1)
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre']);

        return response()->json([
            'sucursales' => $sucursales,
            'motivos'    => MotivoNotaCreditoResource::collection(
                MotivoNotaCredito::activos()->orderBy('mnc_id')->get()
            ),
            'estados'    => [
                ['valor' => NotaCreditoVenta::ESTADO_EMITIDA, 'descripcion' => 'Emitida'],
                ['valor' => NotaCreditoVenta::ESTADO_ANULADA, 'descripcion' => 'Anulada'],
            ],
                'fe_estados' => [
                ['valor' => NotaCreditoVentaFe::ESTADO_PENDIENTE, 'descripcion' => 'Pendiente'],
                ['valor' => NotaCreditoVentaFe::ESTADO_ENVIADO,   'descripcion' => 'Enviada'],
                ['valor' => NotaCreditoVentaFe::ESTADO_RECHAZADO, 'descripcion' => 'Rechazada'],
                ['valor' => 'sin',                                'descripcion' => 'Sin NC electrónica'],
            ],
        ], 200);
    }

    // GET /api/admin/notas-credito-venta?search=&estado=&motivo=&desde=&hasta=
    public function index(Request $request): JsonResponse
    {
        $query = NotaCreditoVenta::with(self::RELACIONES_LISTA);

        if ($request->filled('search')) {
            $search = '%' . strtolower(trim($request->search)) . '%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ncv_razonsocial) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(COALESCE(ncv_ruc, ncv_ci)) LIKE ?', [$search])
                  // El número completo no está almacenado: se arma igual que
                  // en el modelo para que "001-001-0000045" encuentre.
                  ->orWhereRaw(
                      "ncv_establec || '-' || ncv_puntoexp || '-' || LPAD(ncv_numero::text, 7, '0') LIKE ?",
                      [$search]
                  )
                  ->orWhereHas('factura', function ($f) use ($search) {
                      $f->whereRaw(
                          "ven_establec || '-' || ven_puntoexp || '-' || LPAD(ven_numero::text, 7, '0') LIKE ?",
                          [$search]
                      );
                  });
            });
        }

        if ($request->filled('estado')) {
            $query->where('ncv_estado', $request->estado);
        }

        if ($request->filled('motivo')) {
            $query->where('ncv_mncid', $request->motivo);
        }

        if ($request->filled('sucursal')) {
            $query->where('ncv_sucid', $request->sucursal);
        }

        if ($request->filled('desde')) {
            $query->whereDate('ncv_fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('ncv_fecha', '<=', $request->hasta);
        }

        $notas = $query->orderByDesc('ncv_id')->get();

        return response()->json(['data' => NotaCreditoVentaResource::collection($notas)], 200);
    
        // Estado de la declaración electrónica. 'sin' = NC emitidas antes de
        // activar la integración (no tienen registro).
        if ($request->filled('fe_estado')) {
            $request->fe_estado === 'sin'
                ? $query->whereDoesntHave('fe')
                : $query->whereHas('fe', fn ($q) => $q->where('nfe_estado', $request->fe_estado));
        }

    }

    // GET /api/admin/notas-credito-venta/{id}
    public function show(int $id): JsonResponse
    {
        $nota = NotaCreditoVenta::with(self::RELACIONES_DETALLE)->find($id);

        if (!$nota) {
            return response()->json(['message' => 'Nota de crédito no encontrada'], 404);
        }

        return response()->json(['data' => new NotaCreditoVentaResource($nota)], 200);
    }

       // POST /api/admin/notas-credito-venta
    public function store(NotaCreditoVentaRequest $request): JsonResponse
    {
        $datos  = $request->safe()->except('detalle');
        $lineas = $request->input('detalle');

        // "ncv_reingresa" es una decisión del usuario, no una columna:
        // si no reingresa, no hay depósito de destino.
        $reingresa = (bool) ($datos['ncv_reingresa'] ?? false);
        unset($datos['ncv_reingresa']);

        if (!$reingresa) {
            $datos['ncv_depid'] = null;
        }

        try {
            $nota = $this->service->store($datos, $lineas, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $nota->load(self::RELACIONES_DETALLE);

        $mensaje = match (true) {
            !$nota->afectaStock()     => "Nota de Crédito {$nota->ncv_nrodoc} emitida. Se acreditó el importe sin mover stock.",
            $nota->ncv_depid === null => "Nota de Crédito {$nota->ncv_nrodoc} emitida. La mercadería no reingresó al stock.",
            default                   => "Nota de Crédito {$nota->ncv_nrodoc} emitida. La mercadería reingresó al depósito.",
        };
        
        if ($nota->fe) {
            $mensaje .= ' Se está declarando ante la DNIT.';
        }

        return response()->json([
            'message' => $mensaje,
            'data'    => new NotaCreditoVentaResource($nota),
        ], 201);
    }

    // PATCH /api/admin/notas-credito-venta/{id}/anular
    public function anular(int $id, Request $request): JsonResponse
    {
        $nota = NotaCreditoVenta::find($id);

        if (!$nota) {
            return response()->json(['message' => 'Nota de crédito no encontrada'], 404);
        }

        $datos = $request->validate(
            ['motivo' => ['nullable', 'string', 'max:255']],
            ['motivo.max' => 'El motivo no puede superar los 255 caracteres.']
        );

        try {
            $nota = $this->service->anular($nota, $request->user(), $datos['motivo'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $nota->load(self::RELACIONES_DETALLE);

        return response()->json([
            'message' => 'Nota de crédito anulada. El número del timbrado queda consumido.',
            'data'    => new NotaCreditoVentaResource($nota),
        ], 200);
    }

    // =================================================================
    //  Atajo desde el detalle de la factura
    // =================================================================

       /**
     * GET /api/admin/facturas-venta/{id}/notas-credito/config
     *
     * Todo lo que el formulario necesita: motivos (con sus reglas), depósitos
     * de la sucursal emisora y, por línea, los dos saldos.
     */
    public function configPorFactura(int $venId): JsonResponse
    {
        $factura = VentaCab::with('detalle')->find($venId);

        if (!$factura) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        $saldos    = $this->service->saldosPorLinea($venId);
        $decimales = DB::table('unidad_medida')->pluck('umd_decimal', 'umd_codigo');

        $lineas = $factura->detalle->map(function ($d) use ($saldos, $decimales) {
            $saldo = $this->service->saldosDeLinea($d, $saldos);

            return [
                'vnd_id'            => $d->vnd_id,
                'vnd_prdid'         => $d->vnd_prdid,
                'vnd_codigo'        => $d->vnd_codigo,
                'vnd_descripcion'   => $d->vnd_descripcion,
                'vnd_umedida'       => $d->vnd_umedida,
                'vnd_cantidad'      => $d->vnd_cantidad,
                'vnd_precio'        => $d->vnd_precio,
                'vnd_tasa'          => $d->vnd_tasa,
                'vnd_valorventa'    => $d->vnd_valorventa,
                'admite_decimales'  => (bool) ($decimales[$d->vnd_umedida] ?? 0),
                'saldo_acreditable' => $saldo['cantidad'],   // unidades que aún se pueden acreditar
                'saldo_importe'     => $saldo['importe'],    // importe que aún se puede acreditar
            ];

        });

        $depositos = DB::table('deposito')
            ->where('dpo_sucid', $factura->ven_sucid)
            ->where('dpo_estado', 1)
            ->orderByDesc('dpo_princi')
            ->orderBy('dpo_nombre')
            ->get(['dpo_id', 'dpo_nombre', 'dpo_princi']);

        return response()->json([
            'ven_id'          => $factura->ven_id,
            'ven_nrodoc'      => $factura->ven_nrodoc,
            'ven_fchaem'      => $factura->ven_fchaem?->format('Y-m-d'),
            'ven_estado'      => $factura->ven_estado,
            'ven_razonsocial' => $factura->ven_razonsocial,
            'ven_ruc'         => $factura->ven_ruc,
            'ven_ci'          => $factura->ven_ci,
            'ven_total'       => $factura->ven_total,
            'ven_acreditado'  => round($factura->totalAcreditado(), 2),
            'motivos'         => MotivoNotaCreditoResource::collection(
                MotivoNotaCredito::activos()->orderBy('mnc_id')->get()
            ),
            'depositos'       => $depositos,
            'lineas'          => $lineas,
        ], 200);
    }

    // GET /api/admin/facturas-venta/{id}/notas-credito
    public function porFactura(int $venId): JsonResponse
    {
        $notas = NotaCreditoVenta::with(['motivo', 'detalle', 'fe'])
            ->where('ncv_venid', $venId)
            ->orderByDesc('ncv_id')
            ->get();

        return response()->json(['data' => NotaCreditoVentaResource::collection($notas)], 200);
            
    }

        // =================================================================
    //  Nota de crédito electrónica
    // =================================================================

    // GET /api/admin/notas-credito-venta/{id}/fe/kude  (PDF)
    public function kude(int $id): Response
    {
        return $this->archivoFe($id, 'nfe_pdf_ruta', 'pdf', 'application/pdf', 'inline');
    }

    // GET /api/admin/notas-credito-venta/{id}/fe/xml  (documento con valor legal)
    public function xml(int $id): Response
    {
        return $this->archivoFe($id, 'nfe_xml_ruta', 'xml', 'application/xml', 'attachment');
    }

    /**
     * POST /api/admin/notas-credito-venta/{id}/fe/reintentar
     *
     * Solo encola el trabajo. Es idempotente: reintentar una NC ya declarada
     * no la duplica (la API contesta 409 y el servicio la reconoce).
     */
    public function reintentarFe(int $id): JsonResponse
    {
        if (!config('facturacion.activa')) {
            return response()->json(['message' => 'La facturación electrónica está desactivada.'], 409);
        }

        $nota = NotaCreditoVenta::with('fe')->find($id);

        if (!$nota) {
            return response()->json(['message' => 'Nota de crédito no encontrada'], 404);
        }

        if (!$nota->fe) {
            return response()->json([
                'message' => 'Esta nota de crédito se emitió sin facturación electrónica y no se puede declarar.',
            ], 409);
        }

        if (!$nota->fe->estaPendiente()) {
            return response()->json(['message' => 'Solo se pueden reintentar las notas pendientes.'], 409);
        }

        if ($nota->anulada()) {
            return response()->json(['message' => 'La nota de crédito está anulada.'], 409);
        }

        DeclararNotaCreditoElectronica::dispatch($nota->ncv_id);

        return response()->json([
            'message' => 'Reintento en curso. El estado se actualiza en unos segundos.',
        ], 202);
    }

    /**
     * Entrega un archivo del disco privado. Como el panel manda el JWT en
     * una cabecera, el navegador no puede abrirlo con un enlace directo: el
     * front lo pide como Blob.
     */
    private function archivoFe(int $id, string $columna, string $extension, string $mime, string $disposicion): Response
    {
        $nota = NotaCreditoVenta::with('fe')->find($id);

        if (!$nota) {
            return response()->json(['message' => 'Nota de crédito no encontrada'], 404);
        }

        $ruta  = $nota->fe?->{$columna};
        $disco = Storage::disk('local');

        if (!$ruta || !$disco->exists($ruta)) {
            return response()->json([
                'message' => 'El archivo de esta nota de crédito electrónica no está disponible.',
            ], 404);
        }

        return $disco->response($ruta, "NC-{$nota->ncv_nrodoc}.{$extension}", ['Content-Type' => $mime], $disposicion);
    }
}