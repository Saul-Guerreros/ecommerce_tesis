<?php
namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\CuentaPorPagarResource;
use App\Models\Compras\{CompraCab, CuotaCompra};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorPagarController extends Controller
{
    // GET /api/admin/cuentas-por-pagar?search=&proveedor=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = CompraCab::query()
            ->where('com_condic', CompraCab::CONDICION_CREDITO)
            ->where('com_estfac', CompraCab::ESTADO_CONFIRMADA)
            ->withCount('cuotas as cuo_total')
            ->withCount(['cuotas as cuo_pendientes' => fn ($q) => $q->where('cuo_estado', CuotaCompra::ESTADO_PENDIENTE)])
            ->withSum(['cuotas as cuo_saldo' => fn ($q) => $q->where('cuo_estado', CuotaCompra::ESTADO_PENDIENTE)], 'cuo_importe')
            ->withMin(['cuotas as cuo_provencim' => fn ($q) => $q->where('cuo_estado', CuotaCompra::ESTADO_PENDIENTE)], 'cuo_venci');

        if ($request->filled('proveedor')) {
            $query->where('com_proid', $request->proveedor);
        }

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(com_nrodoc) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(com_proraz) LIKE ?', [$search]);
            });
        }

        $facturas = $query->get();

        // Por defecto solo facturas con saldo pendiente. estado=todas trae
        // tambien las que ya se terminaron de pagar (para consulta historica).
        if ($request->input('estado') !== 'todas') {
            $facturas = $facturas->filter(fn ($f) => (float) $f->cuo_saldo > 0)->values();
        }

        $facturas = $facturas->sortBy('cuo_provencim')->values();

        return response()->json(['data' => CuentaPorPagarResource::collection($facturas)], 200);
    }

    // GET /api/admin/cuentas-por-pagar/config
    public function config(): JsonResponse
    {
        $proveedores = DB::table('proveedor')
            ->where('pro_estado', 1)
            ->orderBy('pro_razon')
            ->get(['pro_id', 'pro_razon']);

        return response()->json(['proveedores' => $proveedores], 200);
    }
}