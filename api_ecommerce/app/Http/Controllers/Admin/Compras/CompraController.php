<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\CompraRequest;
use App\Http\Resources\Compra\CompraCabResource;
use App\Models\Compras\CompraCab;
use App\Models\Compras\TimbradoProveedor;
use App\Models\Compras\TipoComprobante;
use App\Services\Compras\CompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function __construct(private CompraService $service)
    {
    }

    // GET /api/admin/compras?search=&proveedor=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = CompraCab::with(['sucursal', 'deposito', 'tipoComprobante']);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(com_nrodoc) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(com_proraz) LIKE ?', [$search]);
            });
        }

        if ($request->filled('proveedor')) {
            $query->where('com_proid', $request->proveedor);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('com_estfac', $request->estado);
        }

        $compras = $query->orderByDesc('com_id')->get();

        return response()->json(['data' => CompraCabResource::collection($compras)], 200);
    }

    /**
     * GET /api/admin/compras/config
     */
    public function config(): JsonResponse
    {
        $proveedores = DB::table('proveedor')
            ->where('pro_estado', 1)
            ->orderBy('pro_razon')
            ->get(['pro_id', 'pro_razon', 'pro_ruc']);

        // Sucursales activas; el deposito se filtra por sucursal en Angular.
        $sucursales = DB::table('sucursal')
            ->where('suc_estado', 1)
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre']);

        $depositos = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->where('dpo_estado', 1)
            ->where('suc_estado', 1)
            ->orderByDesc('dpo_princi')
            ->orderBy('dpo_nombre')
            ->get(['dpo_id', 'dpo_nombre', 'dpo_sucid', 'dpo_princi', 'suc_nombre']);

        // Tipo de comprobante fijo: este formulario solo registra Facturas.
        $tipoFactura = DB::table('tipo_comprobante')
            ->where('tco_codigo', TipoComprobante::CODIGO_FACTURA)
            ->where('tco_estado', 1)
            ->first(['tco_id', 'tco_descri', 'tco_reqtim']);

        $tiposIva = DB::table('tipo_iva')
            ->where('tiv_estado', 1)
            ->orderBy('tiv_tasa')
            ->get(['tiv_id', 'tiv_descri', 'tiv_tasa']);

        return response()->json([
            'proveedores'  => $proveedores,
            'sucursales'   => $sucursales,
            'depositos'    => $depositos,
            'tipo_factura' => $tipoFactura,
            'tipos_iva'    => $tiposIva,
        ], 200);
    }

    // GET /api/admin/compras/{id}
    public function show(int $id): JsonResponse
    {
        $compra = CompraCab::with(['sucursal', 'deposito', 'tipoComprobante', 'detalle.tipoIva', 'detalle.producto', 'detalle.unidadMedida'])->find($id);

        if (!$compra) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        return response()->json(['data' => new CompraCabResource($compra)], 200);
    }

    // POST /api/admin/compras
    public function store(CompraRequest $request): JsonResponse
    {
        $datos = $request->safe()->except('detalle');
        $lineas = $request->input('detalle');

        // Snapshot de proveedor: se copian razon social y RUC al momento
        // de la compra, no se leen por whenLoaded en el futuro.
        $proveedor = DB::table('proveedor')->where('pro_id', $datos['com_proid'])->first();
        $datos['com_proruc'] = $proveedor->pro_ruc;
        $datos['com_proraz'] = $proveedor->pro_razon;

        // Snapshot del timbrado: numero y vencimiento se COPIAN desde
        // timbrado_proveedor (ya validado en CompraRequest), nunca se
        // aceptan escritos a mano desde el frontend.
        $timbrado = !empty($datos['com_tpvid']) ? TimbradoProveedor::find($datos['com_tpvid']) : null;
        $datos['com_timbra'] = $timbrado?->tpv_numero;
        $datos['com_timbvn'] = $timbrado?->tpv_fechfn?->toDateString();

        $compra = $this->service->store($datos, $lineas);
        $compra->load(['sucursal', 'deposito', 'tipoComprobante', 'detalle.tipoIva', 'detalle.producto', 'detalle.unidadMedida']);

        return response()->json([
            'message' => 'Compra registrada correctamente. Stock y costo promedio actualizados.',
            'data'    => new CompraCabResource($compra),
        ], 201);
    }

    // PATCH /api/admin/compras/{id}/anular
    public function anular(int $id): JsonResponse
    {
        $compra = CompraCab::find($id);

        if (!$compra) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        try {
            $compra = $this->service->anular($compra);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $compra->load(['sucursal', 'deposito', 'tipoComprobante', 'detalle.tipoIva', 'detalle.producto', 'detalle.unidadMedida']);

        return response()->json([
            'message' => 'Compra anulada correctamente. Stock revertido.',
            'data'    => new CompraCabResource($compra),
        ], 200);
    }
}