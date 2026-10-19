<?php

namespace App\Http\Controllers\Admin\Venta;

use App\Http\Controllers\Controller;
use App\Http\Resources\Venta\VentaCabResource;
use App\Models\Venta\VentaCab;
use App\Services\Venta\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Facturas de venta: consulta y anulacion.
 *
 * No hay store(): una factura no se crea a mano desde el panel. Nace sola
 * cuando el pedido se cobra o se entrega, dentro de la misma transaccion
 * que descuenta el stock. Por eso este controlador solo expone index,
 * config, show y anular.
 */
class VentaController extends Controller
{
    private const RELACIONES_LISTA   = ['sucursal'];
    private const RELACIONES_DETALLE = ['sucursal', 'caja', 'pedido', 'detalle'];

    public function __construct(private VentaService $service) {}

    /**
     * GET /api/admin/facturas-venta?search=&estado=&desde=&hasta=
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
}