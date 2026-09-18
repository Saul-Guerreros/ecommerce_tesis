<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\LibroCompraResource;
use App\Models\Compras\LibroCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solo lectura (reporte transversal). 'libro_compra' se escribe
 * unicamente desde CompraService::store/anular y NotaCreditoService::store.
 */
class LibroCompraController extends Controller
{
    // GET /api/admin/libro-compra?desde=&hasta=&proveedor=&tipo=&estado=&search=
    public function index(Request $request): JsonResponse
    {
        $query = LibroCompra::query();

        if ($request->filled('desde')) {
            $query->whereDate('lcm_fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('lcm_fecha', '<=', $request->hasta);
        }

        if ($request->filled('tipo')) {
            $query->where('lcm_tipo', $request->tipo);
        }

        // Por defecto solo vigentes: estado=todas trae tambien las anuladas.
        if ($request->input('estado') !== 'todas') {
            $query->where('lcm_estado', LibroCompra::ESTADO_VIGENTE);
        }

        if ($request->filled('proveedor')) {
            $proveedorId = $request->proveedor;
            $query->where(function ($q) use ($proveedorId) {
                $q->whereHas('compraCab', fn ($q2) => $q2->where('com_proid', $proveedorId))
                  ->orWhereHas('notaCredito.compraCab', fn ($q2) => $q2->where('com_proid', $proveedorId));
            });
        }

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(lcm_nrodoc) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(lcm_proraz) LIKE ?', [$search]);
            });
        }

        $registros = $query->orderBy('lcm_fecha')->orderBy('lcm_id')->get();

        return response()->json([
            'data'    => LibroCompraResource::collection($registros),
            'totales' => $this->calcularTotales($registros),
        ], 200);
    }

    // GET /api/admin/libro-compra/config
    public function config(): JsonResponse
    {
        $proveedores = DB::table('proveedor')
            ->where('pro_estado', 1)
            ->orderBy('pro_razon')
            ->get(['pro_id', 'pro_razon']);

        return response()->json(['proveedores' => $proveedores], 200);
    }

    private function calcularTotales($registros): array
    {
        return [
            'exenta' => round((float) $registros->sum('lcm_exenta'), 2),
            'grav5'  => round((float) $registros->sum('lcm_grav5'), 2),
            'grav10' => round((float) $registros->sum('lcm_grav10'), 2),
            'iva5'   => round((float) $registros->sum('lcm_iva5'), 2),
            'iva10'  => round((float) $registros->sum('lcm_iva10'), 2),
            'total'  => round((float) $registros->sum('lcm_total'), 2),
        ];
    }
}