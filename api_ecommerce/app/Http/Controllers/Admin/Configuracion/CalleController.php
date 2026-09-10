<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\CalleRequest;
use App\Http\Resources\Configuracion\CalleResource;
use App\Models\Configuracion\Calle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CalleController extends Controller
{
    /**
     * Tablas que referencian a calle. 'direccion' pertenece al Modulo 1 y
     * todavia no existe; Schema::hasTable() la ignora hasta entonces.
     */
    private const DEPENDENCIAS = [
        'direccion' => ['columna' => 'dir_calid', 'etiqueta' => 'direccion(es)'],
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

            foreach ($conteos as $calId => $total) {
                $usos[$calId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/calles?search=&ciudad=&barrio=&estado=
    public function index(Request $request): JsonResponse
    {
        // barrio.ciudad se precarga porque el Resource expone ciu_descri
        // navegando esa cadena (calle ya no tiene columna cal_ciuid propia).
        $query = Calle::with('barrio.ciudad');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(cal_descri) LIKE ?', [$search]);
        }

        // Sin columna cal_ciuid, filtrar por ciudad requiere subconsulta
        // sobre la relacion barrio (whereHas), no un where directo.
        if ($request->filled('ciudad')) {
            $ciudadId = $request->ciudad;
            $query->whereHas('barrio', fn ($q) => $q->where('bar_ciuid', $ciudadId));
        }

        if ($request->filled('barrio')) {
            $query->where('cal_barid', $request->barrio);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('cal_estado', $request->estado);
        }

        $calles = $query->orderBy('cal_descri')->get();
        $usos   = $this->contarUsos();

        $calles->each(function ($cal) use ($usos) {
            $detalle           = $usos[$cal->cal_id] ?? [];
            $cal->usos_count   = array_sum($detalle);
            $cal->usos_detalle = $detalle;
        });

        return response()->json(['data' => CalleResource::collection($calles)], 200);
    }

    /**
     * GET /api/admin/calles/config
     * Devuelve ciudades y barrios activos en una sola llamada. Los barrios
     * incluyen bar_ciuid para que el formulario filtre el combo de barrio en
     * el cliente al elegir la ciudad (la ciudad sigue siendo un criterio de
     * navegacion en el formulario aunque calle ya no la persista).
     */
    public function config(): JsonResponse
    {
        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        $barrios = DB::table('barrio')
            ->where('bar_estado', 1)
            ->orderBy('bar_descri')
            ->get(['bar_id', 'bar_ciuid', 'bar_descri']);

        return response()->json([
            'ciudades' => $ciudades,
            'barrios'  => $barrios,
        ], 200);
    }

    /**
     * GET /api/admin/calles/select
     * Lista plana para el combo del formulario de direccion (Modulo 1).
     */
    public function select(): JsonResponse
    {
        $calles = DB::table('calle')
            ->join('barrio', 'cal_barid', '=', 'bar_id')
            ->where('cal_estado', 1)
            ->orderBy('cal_descri')
            ->get(['cal_id', 'cal_barid', 'cal_descri', 'bar_descri']);

        return response()->json(['data' => $calles], 200);
    }

    // GET /api/admin/calles/{id}
    public function show(int $id): JsonResponse
    {
        $calle = Calle::with('barrio.ciudad')->find($id);

        if (!$calle) {
            return response()->json(['message' => 'Calle no encontrada'], 404);
        }

        return response()->json(['data' => new CalleResource($calle)], 200);
    }

    // POST /api/admin/calles
    public function store(CalleRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['cal_estado'] = $datos['cal_estado'] ?? 1;

        $calle = Calle::create($datos);
        $calle->load('barrio.ciudad');

        return response()->json([
            'message' => 'Calle registrada correctamente',
            'data'    => new CalleResource($calle),
        ], 201);
    }

    // PUT /api/admin/calles/{id}
    public function update(CalleRequest $request, int $id): JsonResponse
    {
        $calle = Calle::find($id);

        if (!$calle) {
            return response()->json(['message' => 'Calle no encontrada'], 404);
        }

        $calle->update($request->validated());
        $calle->load('barrio.ciudad');

        return response()->json([
            'message' => 'Calle actualizada correctamente',
            'data'    => new CalleResource($calle),
        ], 200);
    }

    // DELETE /api/admin/calles/{id}
    public function destroy(int $id): JsonResponse
    {
        $calle = Calle::find($id);

        if (!$calle) {
            return response()->json(['message' => 'Calle no encontrada'], 404);
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
                'message' => 'No se puede eliminar: la calle esta siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactivela en lugar de eliminarla.',
            ], 409);
        }

        $calle->delete();

        return response()->json(['message' => 'Calle eliminada correctamente'], 200);
    }

    // PATCH /api/admin/calles/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $calle = Calle::find($id);

        if (!$calle) {
            return response()->json(['message' => 'Calle no encontrada'], 404);
        }

        $calle->cal_estado = $calle->cal_estado == 1 ? 0 : 1;
        $calle->save();
        $calle->load('barrio.ciudad');

        return response()->json([
            'message' => $calle->cal_estado == 1
                ? 'Calle activada correctamente'
                : 'Calle desactivada correctamente',
            'data'    => new CalleResource($calle),
        ], 200);
    }
}
