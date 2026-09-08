<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\DepartamentoRequest;
use App\Http\Resources\Configuracion\DepartamentoResource;
use App\Models\Configuracion\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartamentoController extends Controller
{
    /**
     * Tablas que referencian a departamento.
     */
    private const DEPENDENCIAS = [
        'ciudad' => ['columna' => 'ciu_depid', 'etiqueta' => 'ciudad(es)'],
    ];

    /**
     * Cuenta cuantos registros de otras tablas apuntan a cada departamento.
     * Devuelve [dep_id => ['ciudad' => 5]]
     */
    private function contarUsos(): array
    {
        $usos = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);

            foreach ($conteos as $depId => $total) {
                $usos[$depId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/departamentos?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Departamento::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(dep_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('dep_estado', $request->estado);
        }

        $departamentos = $query->orderBy('dep_descri')->get();
        $usos          = $this->contarUsos();

        $departamentos->each(function ($dep) use ($usos) {
            $detalle            = $usos[$dep->dep_id] ?? [];
            $dep->usos_count    = array_sum($detalle);
            $dep->usos_detalle  = $detalle;
        });

        return response()->json(['data' => DepartamentoResource::collection($departamentos)], 200);
    }

    /**
     * GET /api/admin/departamentos/select
     * Lista plana de departamentos activos para el <select> de ciudad.
     */
    public function select(): JsonResponse
    {
        $departamentos = Departamento::where('dep_estado', 1)
            ->orderBy('dep_descri')
            ->get(['dep_id', 'dep_descri']);

        return response()->json(['data' => $departamentos], 200);
    }

    // GET /api/admin/departamentos/{id}
    public function show(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        return response()->json(['data' => new DepartamentoResource($departamento)], 200);
    }

    // POST /api/admin/departamentos
    public function store(DepartamentoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['dep_estado'] = $datos['dep_estado'] ?? 1;

        $departamento = Departamento::create($datos);

        return response()->json([
            'message' => 'Departamento registrado correctamente',
            'data'    => new DepartamentoResource($departamento),
        ], 201);
    }

    // PUT /api/admin/departamentos/{id}
    public function update(DepartamentoRequest $request, int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        $departamento->update($request->validated());

        return response()->json([
            'message' => 'Departamento actualizado correctamente',
            'data'    => new DepartamentoResource($departamento->fresh()),
        ], 200);
    }

    // DELETE /api/admin/departamentos/{id}
    public function destroy(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        $detalle = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $total = DB::table($tabla)->where($info['columna'], $id)->count();

            if ($total > 0) {
                $detalle[] = "{$total} {$info['etiqueta']}";
            }
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el departamento esta siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        $departamento->delete();

        return response()->json(['message' => 'Departamento eliminado correctamente'], 200);
    }

    // PATCH /api/admin/departamentos/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        $departamento->dep_estado = $departamento->dep_estado == 1 ? 0 : 1;
        $departamento->save();

        return response()->json([
            'message' => $departamento->dep_estado == 1
                ? 'Departamento activado correctamente'
                : 'Departamento desactivado correctamente',
            'data'    => new DepartamentoResource($departamento),
        ], 200);
    }
}
