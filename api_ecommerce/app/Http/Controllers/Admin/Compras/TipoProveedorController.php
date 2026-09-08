<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\TipoProveedorRequest;
use App\Http\Resources\Compra\TipoProveedorResource;
use App\Models\Compras\TipoProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoProveedorController extends Controller
{
    private const DEPENDENCIAS = [
        'proveedor' => ['columna' => 'pro_tprid', 'etiqueta' => 'proveedor(es)'],
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

            foreach ($conteos as $id => $total) {
                $usos[$id][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/tipos-proveedor?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoProveedor::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(tpr_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tpr_estado', $request->estado);
        }

        $tipos = $query->orderBy('tpr_descri')->get();
        $usos  = $this->contarUsos();

        $tipos->each(function ($t) use ($usos) {
            $t->usos_count = array_sum($usos[$t->tpr_id] ?? []);
        });

        return response()->json(['data' => TipoProveedorResource::collection($tipos)], 200);
    }

    // GET /api/admin/tipos-proveedor/select
    public function select(): JsonResponse
    {
        $tipos = TipoProveedor::where('tpr_estado', 1)
            ->orderBy('tpr_descri')
            ->get(['tpr_id', 'tpr_descri']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-proveedor/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);

        return response()->json(['data' => new TipoProveedorResource($tipo)], 200);
    }

    // POST /api/admin/tipos-proveedor
    public function store(TipoProveedorRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['tpr_estado'] = $datos['tpr_estado'] ?? 1;

        $tipo = TipoProveedor::create($datos);

        return response()->json([
            'message' => 'Tipo de proveedor registrado correctamente',
            'data'    => new TipoProveedorResource($tipo),
        ], 201);
    }

    // PUT /api/admin/tipos-proveedor/{id}
    public function update(TipoProveedorRequest $request, int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);

        $tipo->update($request->validated());

        return response()->json([
            'message' => 'Tipo de proveedor actualizado correctamente',
            'data'    => new TipoProveedorResource($tipo->fresh()),
        ], 200);
    }

    // DELETE /api/admin/tipos-proveedor/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de proveedor está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de proveedor eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-proveedor/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);

        $tipo->tpr_estado = $tipo->tpr_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tpr_estado == 1 ? 'Tipo de proveedor activado correctamente' : 'Tipo de proveedor desactivado correctamente',
            'data'    => new TipoProveedorResource($tipo),
        ], 200);
    }
}