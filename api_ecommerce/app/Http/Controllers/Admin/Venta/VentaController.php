<?php

namespace App\Http\Controllers\Admin\Venta;

use App\Http\Controllers\Controller;
use App\Http\Resources\Venta\VentaCabResource;
use App\Jobs\DeclararFacturaElectronica;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use App\Services\Venta\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facturas de venta: consulta y anulacion.
 *
 * No hay store(): una factura no se crea a mano desde el panel. Nace sola
 * cuando el pedido se cobra o se entrega, dentro de la misma transaccion
 * que descuenta el stock. Por eso este controlador solo expone index,
 * config, show y anular.
 *
 * Factura electronica: ademas permite ver su estado, bajar el KuDE y el XML
 * y reintentar una declaracion pendiente. Los archivos estan en un disco
 * privado (storage/app), asi que NO tienen URL publica: solo se entregan
 * por estas rutas, con JWT y permiso.
 */
class VentaController extends Controller
{
    private const RELACIONES_LISTA   = ['sucursal', 'fe'];
    private const RELACIONES_DETALLE = ['sucursal', 'caja', 'pedido', 'detalle', 'fe'];

    public function __construct(private VentaService $service) {}

    /**
     * GET /api/admin/facturas-venta?search=&estado=&fe_estado=&desde=&hasta=
     *
     * fe_estado: 1 Pendiente, 2 Enviada, 3 Rechazada, "sin" = sin factura electronica.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VentaCab::with(self::RELACIONES_LISTA);

        if ($request->filled('search')) {
            $search = '%' . strtolower(trim($request->search)) . '%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ven_razonsocial) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(COALESCE(ven_ruc, ven_ci)) LIKE ?', [$search])
                  // El numero completo no esta almacenado: se arma igual
                  // que en el modelo para que "001-001-0000012" encuentre.
                  ->orWhereRaw(
                      "ven_establec || '-' || ven_puntoexp || '-' || LPAD(ven_numero::text, 7, '0') LIKE ?",
                      [$search]
                  );
            });
        }

        if ($request->filled('estado')) {
            $query->where('ven_estado', $request->estado);
        }

        if ($request->filled('fe_estado')) {
            if ($request->fe_estado === 'sin') {
                $query->whereDoesntHave('fe');
            } else {
                $query->whereHas('fe', fn ($q) => $q->where('vfe_estado', (int) $request->fe_estado));
            }
        }

        if ($request->filled('desde')) {
            $query->whereDate('ven_fchaem', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('ven_fchaem', '<=', $request->hasta);
        }

        $ventas = $query->orderByDesc('ven_id')->get();

        return response()->json(['data' => VentaCabResource::collection($ventas)], 200);
    }

    /**
     * GET /api/admin/facturas-venta/config
     *
     * Combos del listado. Excepcion documentada del contrato de la API:
     * devuelve claves sueltas, no { data }.
     */
    public function config(): JsonResponse
    {
        $sucursales = DB::table('sucursal')
            ->where('suc_estado', 1)
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre']);

        return response()->json([
            'sucursales' => $sucursales,
            'estados'    => [
                ['valor' => VentaCab::ESTADO_EMITIDA, 'descripcion' => 'Emitida'],
                ['valor' => VentaCab::ESTADO_COBRADA, 'descripcion' => 'Cobrada'],
                ['valor' => VentaCab::ESTADO_ANULADA, 'descripcion' => 'Anulada'],
            ],
            'fe_estados' => [
                ['valor' => VentaFe::ESTADO_PENDIENTE, 'descripcion' => 'Pendiente'],
                ['valor' => VentaFe::ESTADO_ENVIADO,   'descripcion' => 'Enviada'],
                ['valor' => VentaFe::ESTADO_RECHAZADO, 'descripcion' => 'Rechazada'],
                ['valor' => 'sin',                     'descripcion' => 'Sin factura electrónica'],
            ],
        ], 200);
    }

    // GET /api/admin/facturas-venta/{id}
    public function show(int $id): JsonResponse
    {
        $venta = VentaCab::with(self::RELACIONES_DETALLE)->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        return response()->json(['data' => new VentaCabResource($venta)], 200);
    }

    // PATCH /api/admin/facturas-venta/{id}/anular
    public function anular(int $id, Request $request): JsonResponse
    {
        $venta = VentaCab::find($id);

        if (!$venta) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        $datos = $request->validate(
            ['motivo' => ['nullable', 'string', 'max:255']],
            ['motivo.max' => 'El motivo no puede superar los 255 caracteres.']
        );

        $venta = $this->service->anular($venta, $request->user(), $datos['motivo'] ?? null);
        $venta->load(self::RELACIONES_DETALLE);

        return response()->json([
            'message' => 'Factura anulada. El número del timbrado queda consumido, como corresponde a un documento fiscal.',
            'data'    => new VentaCabResource($venta),
        ], 200);
    }

    // ---------- Factura electronica ----------

    // GET /api/admin/facturas-venta/{id}/fe/kude  (PDF)
    public function kude(int $id): Response
    {
        return $this->archivoFe($id, 'vfe_pdf_ruta', 'pdf', 'application/pdf', 'inline');
    }

    // GET /api/admin/facturas-venta/{id}/fe/xml  (documento con valor legal)
    public function xml(int $id): Response
    {
        return $this->archivoFe($id, 'vfe_xml_ruta', 'xml', 'application/xml', 'attachment');
    }

    /**
     * POST /api/admin/facturas-venta/{id}/fe/reintentar
     *
     * Solo encola el trabajo: la llamada a la API no se hace en esta
     * peticion. Es idempotente: reintentar una factura que ya se declaro
     * no la duplica (la API contesta 409 y el servicio la reconoce).
     */
    public function reintentarFe(int $id): JsonResponse
    {
        if (!config('facturacion.activa')) {
            return response()->json(['message' => 'La facturación electrónica está desactivada.'], 409);
        }

        $venta = VentaCab::with('fe')->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        if (!$venta->fe) {
            return response()->json([
                'message' => 'Esta factura se emitió sin facturación electrónica y no se puede declarar.',
            ], 409);
        }

        if (!$venta->fe->estaPendiente()) {
            return response()->json(['message' => 'Solo se pueden reintentar las facturas pendientes.'], 409);
        }

        if ($venta->anulada()) {
            return response()->json(['message' => 'La factura está anulada.'], 409);
        }

        DeclararFacturaElectronica::dispatch($venta->ven_id);

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
        $venta = VentaCab::with('fe')->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        $ruta  = $venta->fe?->{$columna};
        $disco = Storage::disk('local');

        if (!$ruta || !$disco->exists($ruta)) {
            return response()->json([
                'message' => 'El archivo de esta factura electrónica no está disponible.',
            ], 404);
        }

        return $disco->response($ruta, "{$venta->ven_nrodoc}.{$extension}", ['Content-Type' => $mime], $disposicion);
    }
}