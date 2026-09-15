<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\TipoAjusteRequest;
use App\Http\Resources\Compra\TipoAjusteResource;
use App\Models\Compras\TipoAjuste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoAjusteController extends Controller
{
    private const DEPENDENCIAS = [
        'ajuste_inventario' => ['columna' => 'aji_tpaid', 'etiqueta' => 'ajuste(s) de inventario'],
    ];

    private function contarUsos(): array
    {
        $usos = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);
            foreach ($conteos as $id => $total) $usos[$id][$tabla] = (int) $total;
        }
        return $usos;
    }

    // GET /api/admin/tipos-ajuste?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoAjuste::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(tpa_codig) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(tpa_descri) LIKE ?', [$search]);
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tpa_estado', $request->estado);
        }

        $tipos = $query->orderBy('tpa_descri')->get();
        $usos  = $this->contarUsos();

        $tipos->each(fn($t) => $t->usos_count = array_sum($usos[$t->tpa_id] ?? []));

        return response()->json(['data' => TipoAjusteResource::collection($tipos)], 200);
    }

    // GET /api/admin/tipos-ajuste/select
    public function select(): JsonResponse
    {
        $tipos = TipoAjuste::where('tpa_estado', 1)
            ->orderBy('tpa_descri')
            ->get(['tpa_id', 'tpa_codig', 'tpa_descri', 'tpa_natura', 'tpa_reqcom']);

        return response()->json(['data' => $tipos], 200);
    }

    public function show(int $id): JsonResponse
    {
        $tipo = TipoAjuste::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de ajuste no encontrado'], 404);
        return response()->json(['data' => new TipoAjusteResource($tipo)], 200);
    }

    public function store(TipoAjusteRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['tpa_estado'] = $datos['tpa_estado'] ?? 1;
        $datos['tpa_reqcom'] = $datos['tpa_reqcom'] ?? 0;

        $tipo = TipoAjuste::create($datos);

        return response()->json([
            'message' => 'Tipo de ajuste registrado correctamente',
            'data'    => new TipoAjusteResource($tipo),
        ], 201);
    }

    public function update(TipoAjusteRequest $request, int $id): JsonResponse
    {
        $tipo = TipoAjuste::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de ajuste no encontrado'], 404);

        $tipo->update($request->validated());

        return response()->json([
            'message' => 'Tipo de ajuste actualizado correctamente',
            'data'    => new TipoAjusteResource($tipo->fresh()),
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoAjuste::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de ajuste no encontrado'], 404);

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de ajuste está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();
        return response()->json(['message' => 'Tipo de ajuste eliminado correctamente'], 200);
    }

    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoAjuste::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de ajuste no encontrado'], 404);

        $tipo->tpa_estado = $tipo->tpa_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tpa_estado == 1 ? 'Tipo de ajuste activado correctamente' : 'Tipo de ajuste desactivado correctamente',
            'data'    => new TipoAjusteResource($tipo),
        ], 200);
    }
}