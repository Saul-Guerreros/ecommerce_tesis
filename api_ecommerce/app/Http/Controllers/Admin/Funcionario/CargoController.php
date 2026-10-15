<?php

namespace App\Http\Controllers\Admin\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\CargoRequest;
use App\Http\Resources\Funcionario\CargoResource;
use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CargoController extends Controller
{
    /** Tablas que referencian a cargo (para "en uso" y bloqueo de borrado). */
    private const DEPENDENCIAS = [
        'funcionario' => ['columna' => 'fun_crgid', 'etiqueta' => 'funcionario(s)'],
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

    // GET /api/admin/cargos?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Cargo::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(crg_descr) LIKE ?', [$search]);
        }

        if ($request->filled('estado')) {
            $query->where('crg_estado', $request->estado);
        }

        $cargos = $query->orderBy('crg_descr')->get();
        $usos   = $this->contarUsos();

        $cargos->each(function ($c) use ($usos) {
            $c->usos_count = array_sum($usos[$c->crg_id] ?? []);
        });

        return response()->json(['data' => CargoResource::collection($cargos)], 200);
    }

    // GET /api/admin/cargos/select
    public function select(): JsonResponse
    {
        $cargos = Cargo::where('crg_estado', 1)
            ->orderBy('crg_descr')
            ->get(['crg_id', 'crg_descr']);

        return response()->json(['data' => $cargos], 200);
    }

    // GET /api/admin/cargos/{id}
    public function show(int $id): JsonResponse
    {
        $cargo = Cargo::find($id);
        if (!$cargo) return response()->json(['message' => 'Cargo no encontrado'], 404);

        return response()->json(['data' => new CargoResource($cargo)], 200);
    }

    // POST /api/admin/cargos
    public function store(CargoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['crg_estado'] = $datos['crg_estado'] ?? 1;

        $cargo = Cargo::create($datos);

        return response()->json([
            'message' => 'Cargo registrado correctamente',
            'data'    => new CargoResource($cargo->fresh()),
        ], 201);
    }

    // PUT /api/admin/cargos/{id}
    public function update(CargoRequest $request, int $id): JsonResponse
    {
        $cargo = Cargo::find($id);
        if (!$cargo) return response()->json(['message' => 'Cargo no encontrado'], 404);

        $cargo->update($request->validated());

        return response()->json([
            'message' => 'Cargo actualizado correctamente',
            'data'    => new CargoResource($cargo->fresh()),
        ], 200);
    }

    // DELETE /api/admin/cargos/{id}
    public function destroy(int $id): JsonResponse
    {
        $cargo = Cargo::find($id);
        if (!$cargo) return response()->json(['message' => 'Cargo no encontrado'], 404);

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el cargo está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $cargo->delete();

        return response()->json(['message' => 'Cargo eliminado correctamente'], 200);
    }

    // PATCH /api/admin/cargos/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $cargo = Cargo::find($id);
        if (!$cargo) return response()->json(['message' => 'Cargo no encontrado'], 404);

        $cargo->crg_estado = $cargo->crg_estado == 1 ? 0 : 1;
        $cargo->save();

        return response()->json([
            'message' => $cargo->crg_estado == 1
                ? 'Cargo activado correctamente'
                : 'Cargo desactivado correctamente',
            'data'    => new CargoResource($cargo),
        ], 200);
    }
}
