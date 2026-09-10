<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\UnidadMedidaRequest;
use App\Http\Resources\Compra\UnidadMedidaResource;
use App\Models\Compras\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnidadMedidaController extends Controller
{
    /**
     * 'producto' se declara aunque prd_umdid todavia no exista: cuando se
     * migre producto al DER nuevo, Schema::hasColumn deja de omitirla sin
     * tocar este controlador.
     */
    private const DEPENDENCIAS = [
        'producto' => ['columna' => 'prd_umdid', 'etiqueta' => 'producto(s)'],
    ];

    private function contarUsos(): array
    {
        $usos = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, $info['columna'])) {
                continue;
            }

            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);

            foreach ($conteos as $umdId => $total) {
                $usos[$umdId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/unidades-medida?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = UnidadMedida::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(umd_nombre) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(umd_codigo) LIKE ?', [$search]);
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('umd_estado', $request->estado);
        }

        $unidades = $query->orderBy('umd_nombre')->get();
        $usos     = $this->contarUsos();

        $unidades->each(function ($u) use ($usos) {
            $u->usos_count = array_sum($usos[$u->umd_id] ?? []);
        });

        return response()->json(['data' => UnidadMedidaResource::collection($unidades)], 200);
    }

    // GET /api/admin/unidades-medida/select
    public function select(): JsonResponse
    {
        $unidades = UnidadMedida::where('umd_estado', 1)
            ->orderBy('umd_nombre')
            ->get(['umd_id', 'umd_codigo', 'umd_nombre', 'umd_decimal']);

        return response()->json(['data' => $unidades], 200);
    }

    // GET /api/admin/unidades-medida/{id}
    public function show(int $id): JsonResponse
    {
        $unidad = UnidadMedida::find($id);

        if (!$unidad) {
            return response()->json(['message' => 'Unidad de medida no encontrada'], 404);
        }

        return response()->json(['data' => new UnidadMedidaResource($unidad)], 200);
    }

    // POST /api/admin/unidades-medida
    public function store(UnidadMedidaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['umd_estado']  = $datos['umd_estado'] ?? 1;
        $datos['umd_decimal'] = $datos['umd_decimal'] ?? 0;

        $unidad = UnidadMedida::create($datos);

        return response()->json([
            'message' => 'Unidad de medida registrada correctamente',
            'data'    => new UnidadMedidaResource($unidad),
        ], 201);
    }

    // PUT /api/admin/unidades-medida/{id}
    public function update(UnidadMedidaRequest $request, int $id): JsonResponse
    {
        $unidad = UnidadMedida::find($id);

        if (!$unidad) {
            return response()->json(['message' => 'Unidad de medida no encontrada'], 404);
        }

        $unidad->update($request->validated());

        return response()->json([
            'message' => 'Unidad de medida actualizada correctamente',
            'data'    => new UnidadMedidaResource($unidad->fresh()),
        ], 200);
    }

    // DELETE /api/admin/unidades-medida/{id}
    public function destroy(int $id): JsonResponse
    {
        $unidad = UnidadMedida::find($id);

        if (!$unidad) {
            return response()->json(['message' => 'Unidad de medida no encontrada'], 404);
        }

        $detalle = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, $info['columna'])) {
                continue;
            }

            $total = DB::table($tabla)->where($info['columna'], $id)->count();

            if ($total > 0) {
                $detalle[] = "{$total} {$info['etiqueta']}";
            }
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: la unidad está siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
            ], 409);
        }

        $unidad->delete();

        return response()->json(['message' => 'Unidad de medida eliminada correctamente'], 200);
    }

    // PATCH /api/admin/unidades-medida/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $unidad = UnidadMedida::find($id);

        if (!$unidad) {
            return response()->json(['message' => 'Unidad de medida no encontrada'], 404);
        }

        $unidad->umd_estado = $unidad->umd_estado == 1 ? 0 : 1;
        $unidad->save();

        return response()->json([
            'message' => $unidad->umd_estado == 1
                ? 'Unidad de medida activada correctamente'
                : 'Unidad de medida desactivada correctamente',
            'data'    => new UnidadMedidaResource($unidad),
        ], 200);
    }
}