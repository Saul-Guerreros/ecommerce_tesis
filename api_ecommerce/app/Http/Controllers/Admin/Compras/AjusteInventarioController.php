<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\AjusteInventarioRequest;
use App\Http\Resources\Compra\AjusteInventarioResource;
use App\Models\Compras\AjusteInventario;
use App\Services\Compras\AjusteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AjusteInventarioController extends Controller
{
    public function __construct(private AjusteService $service)
    {
    }

    // GET /api/admin/ajustes-inventario?search=&deposito=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = AjusteInventario::with(['tipoAjuste', 'deposito']);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(aji_referencia) LIKE ?', [$search]);
        }

        if ($request->filled('deposito')) {
            $query->where('aji_depid', $request->deposito);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('aji_estado', $request->estado);
        }

        $ajustes = $query->orderByDesc('aji_id')->get();

        return response()->json(['data' => AjusteInventarioResource::collection($ajustes)], 200);
    }

    // GET /api/admin/ajustes-inventario/config
    public function config(): JsonResponse
    {
        $tipos = DB::table('tipo_ajuste')
            ->where('tpa_estado', 1)
            ->orderBy('tpa_descri')
            ->get(['tpa_id', 'tpa_codig', 'tpa_descri', 'tpa_natura', 'tpa_reqcom']);

        $depositos = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->where('dpo_estado', 1)
            ->orderByDesc('dpo_princi')
            ->orderBy('dpo_nombre')
            ->get(['dpo_id', 'dpo_nombre', 'dpo_sucid', 'suc_nombre']);

        return response()->json([
            'tipos'      => $tipos,
            'depositos'  => $depositos,
        ], 200);
    }

    // GET /api/admin/ajustes-inventario/{id}
    public function show(int $id): JsonResponse
    {
        $ajuste = AjusteInventario::with(['tipoAjuste', 'deposito', 'detalle.producto'])->find($id);

        if (!$ajuste) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        return response()->json(['data' => new AjusteInventarioResource($ajuste)], 200);
    }

    // POST /api/admin/ajustes-inventario  (crea como Borrador)
    public function store(AjusteInventarioRequest $request): JsonResponse
    {
        $datos = $request->safe()->except('detalle');
        $lineas = $request->input('detalle');

        $ajuste = $this->service->store($datos, $lineas, (int) $datos['aji_depid']);
        $ajuste->load(['tipoAjuste', 'deposito', 'detalle.producto']);

        return response()->json([
            'message' => 'Ajuste guardado como borrador. Todavía no impacta el stock.',
            'data'    => new AjusteInventarioResource($ajuste),
        ], 201);
    }

    // PATCH /api/admin/ajustes-inventario/{id}/confirmar
    public function confirmar(int $id): JsonResponse
    {
        $ajuste = AjusteInventario::find($id);

        if (!$ajuste) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        try {
            $ajuste = $this->service->confirmar($ajuste);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $ajuste->load(['tipoAjuste', 'deposito', 'detalle.producto']);

        return response()->json([
            'message' => 'Ajuste confirmado correctamente. Stock y costo promedio actualizados.',
            'data'    => new AjusteInventarioResource($ajuste),
        ], 200);
    }

    // PATCH /api/admin/ajustes-inventario/{id}/anular
    public function anular(int $id): JsonResponse
    {
        $ajuste = AjusteInventario::find($id);

        if (!$ajuste) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        try {
            $ajuste = $this->service->anular($ajuste);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $ajuste->load(['tipoAjuste', 'deposito', 'detalle.producto']);

        return response()->json([
            'message' => 'Ajuste anulado correctamente. Stock revertido.',
            'data'    => new AjusteInventarioResource($ajuste),
        ], 200);
    }

    // DELETE /api/admin/ajustes-inventario/{id}  (solo Borrador)
    public function destroy(int $id): JsonResponse
    {
        $ajuste = AjusteInventario::find($id);

        if (!$ajuste) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        try {
            $this->service->eliminarBorrador($ajuste);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Borrador eliminado correctamente'], 200);
    }
}