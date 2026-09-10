<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\BarrioRequest;
use App\Http\Resources\Configuracion\BarrioResource;
use App\Models\Configuracion\Barrio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarrioController extends Controller
{
    /**
     * Tablas que referencian a barrio. 'direccion' se declara desde ya
     * (Modulo 1) aunque la tabla todavia no exista: Schema::hasTable() la
     * ignora hasta que se cree la migracion correspondiente.
     */
    private const DEPENDENCIAS = [
        'calle'     => ['columna' => 'cal_barid', 'etiqueta' => 'calle(s)'],
        'direccion' => ['columna' => 'dir_barid', 'etiqueta' => 'direccion(es)'],
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

            foreach ($conteos as $barId => $total) {
                $usos[$barId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/barrios?search=&ciudad=&estado=
    public function index(Request $request): JsonResponse
    {
        // ciudad.departamento se precarga porque el Resource muestra el
        // departamento para desambiguar barrios homonimos entre ciudades.
        $query = Barrio::with('ciudad.departamento');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(bar_descri) LIKE ?', [$search]);
        }

        if ($request->filled('ciudad')) {
            $query->where('bar_ciuid', $request->ciudad);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('bar_estado', $request->estado);
        }

        $barrios = $query->orderBy('bar_descri')->get();
        $usos    = $this->contarUsos();

        $barrios->each(function ($bar) use ($usos) {
            $detalle           = $usos[$bar->bar_id] ?? [];
            $bar->usos_count   = array_sum($detalle);
            $bar->usos_detalle = $detalle;
        });

        return response()->json(['data' => BarrioResource::collection($barrios)], 200);
    }

    /**
     * GET /api/admin/barrios/config
     * Ciudades activas para el <select> del formulario de barrio.
     */
    public function config(): JsonResponse
    {
        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        return response()->json(['ciudades' => $ciudades], 200);
    }

    /**
     * GET /api/admin/barrios/select
     * Lista plana para los combos de OTROS formularios (calle, direccion).
     * Incluye bar_ciuid para que el frontend pueda filtrar por ciudad sin
     * pedir otra vez al servidor.
     */
    public function select(): JsonResponse
    {
        $barrios = DB::table('barrio')
            ->join('ciudad', 'bar_ciuid', '=', 'ciu_id')
            ->where('bar_estado', 1)
            ->orderBy('ciu_descri')
            ->orderBy('bar_descri')
            ->get(['bar_id', 'bar_ciuid', 'bar_descri', 'ciu_descri']);

        return response()->json(['data' => $barrios], 200);
    }

    // GET /api/admin/barrios/{id}
    public function show(int $id): JsonResponse
    {
        $barrio = Barrio::with('ciudad.departamento')->find($id);

        if (!$barrio) {
            return response()->json(['message' => 'Barrio no encontrado'], 404);
        }

        return response()->json(['data' => new BarrioResource($barrio)], 200);
    }

    // POST /api/admin/barrios
    public function store(BarrioRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['bar_estado'] = $datos['bar_estado'] ?? 1;

        $barrio = Barrio::create($datos);

        return response()->json([
            'message' => 'Barrio registrado correctamente',
            'data'    => new BarrioResource($barrio),
        ], 201);
    }

    // PUT /api/admin/barrios/{id}
    public function update(BarrioRequest $request, int $id): JsonResponse
    {
        $barrio = Barrio::find($id);

        if (!$barrio) {
            return response()->json(['message' => 'Barrio no encontrado'], 404);
        }

        $barrio->update($request->validated());

        return response()->json([
            'message' => 'Barrio actualizado correctamente',
            'data'    => new BarrioResource($barrio->fresh()),
        ], 200);
    }

    // DELETE /api/admin/barrios/{id}
    public function destroy(int $id): JsonResponse
    {
        $barrio = Barrio::find($id);

        if (!$barrio) {
            return response()->json(['message' => 'Barrio no encontrado'], 404);
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
                'message' => 'No se puede eliminar: el barrio esta siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        $barrio->delete();

        return response()->json(['message' => 'Barrio eliminado correctamente'], 200);
    }

    // PATCH /api/admin/barrios/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $barrio = Barrio::find($id);

        if (!$barrio) {
            return response()->json(['message' => 'Barrio no encontrado'], 404);
        }

        $barrio->bar_estado = $barrio->bar_estado == 1 ? 0 : 1;
        $barrio->save();

        return response()->json([
            'message' => $barrio->bar_estado == 1
                ? 'Barrio activado correctamente'
                : 'Barrio desactivado correctamente',
            'data'    => new BarrioResource($barrio),
        ], 200);
    }
}
