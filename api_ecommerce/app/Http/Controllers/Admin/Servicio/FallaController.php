<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\FallaRequest;
use App\Http\Resources\Servicio\FallaResource;
use App\Models\Servicio\Falla;
use App\Models\Servicio\TipoDispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FallaController extends Controller
{
    private const DEPENDENCIAS = [
        'diagnostico_falla' => ['columna' => 'dfa_fllid', 'etiqueta' => 'diagnóstico(s)'],
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

    private function detalleUsos(int $id): array
    {
        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }
        return $detalle;
    }

    // GET /api/admin/fallas?search=&estado=&tdi_id=   (tdi_id = 'GEN' filtra las genéricas)
    public function index(Request $request): JsonResponse
    {
        $query = Falla::with('tipoDispositivo');

        if ($request->filled('search')) {
            $search = '%' . mb_strtolower($request->search) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(fll_codigo) LIKE ?', [$search])
                                       ->orWhereRaw('LOWER(fll_descri) LIKE ?', [$search]));
        }
        if ($request->filled('estado')) {
            $query->where('fll_estado', $request->estado);
        }
        if ($request->filled('tdi_id')) {
            $request->tdi_id === 'GEN'
                ? $query->whereNull('fll_tdiid')
                : $query->where('fll_tdiid', (int) $request->tdi_id);
        }

        $fallas = $query->orderBy('fll_descri')->get();
        $usos   = $this->contarUsos();
        $fallas->each(fn ($f) => $f->usos_count = array_sum($usos[$f->fll_id] ?? []));

        return response()->json(['data' => FallaResource::collection($fallas)], 200);
    }

    // GET /api/admin/fallas/config  — combos del formulario
    public function config(): JsonResponse
    {
        return response()->json([
            'tipos_dispositivo' => TipoDispositivo::activos()->orderBy('tdi_descri')
                ->get(['tdi_id', 'tdi_codigo', 'tdi_descri']),
        ], 200);
    }

    // GET /api/admin/fallas/select?tdi_id=  — lo usará el formulario de diagnóstico (T2)
    public function select(Request $request): JsonResponse
    {
        $fallas = Falla::activas()
            ->paraTipo($request->filled('tdi_id') ? (int) $request->tdi_id : null)
            ->orderBy('fll_descri')
            ->get(['fll_id', 'fll_codigo', 'fll_descri', 'fll_tdiid']);

        return response()->json(['data' => $fallas], 200);
    }

    public function show(int $id): JsonResponse
    {
        $falla = Falla::with('tipoDispositivo')->find($id);
        if (!$falla) return response()->json(['message' => 'Falla no encontrada'], 404);

        return response()->json(['data' => new FallaResource($falla)], 200);
    }

    public function store(FallaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['fll_estado'] = $datos['fll_estado'] ?? 1;

        $falla = Falla::create($datos);

        return response()->json([
            'message' => 'Falla registrada correctamente',
            'data'    => new FallaResource($falla->fresh('tipoDispositivo')),
        ], 201);
    }

    public function update(FallaRequest $request, int $id): JsonResponse
    {
        $falla = Falla::find($id);
        if (!$falla) return response()->json(['message' => 'Falla no encontrada'], 404);

        $falla->update($request->validated());

        return response()->json([
            'message' => 'Falla actualizada correctamente',
            'data'    => new FallaResource($falla->fresh('tipoDispositivo')),
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $falla = Falla::find($id);
        if (!$falla) return response()->json(['message' => 'Falla no encontrada'], 404);

        $detalle = $this->detalleUsos($id);
        if ($detalle) {
            return response()->json([
                'message' => 'No se puede eliminar: la falla está registrada en '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
            ], 409);
        }

        $falla->delete();

        return response()->json(['message' => 'Falla eliminada correctamente'], 200);
    }

    public function toggleEstado(int $id): JsonResponse
    {
        $falla = Falla::with('tipoDispositivo')->find($id);
        if (!$falla) return response()->json(['message' => 'Falla no encontrada'], 404);

        $falla->fll_estado = $falla->fll_estado == 1 ? 0 : 1;
        $falla->save();

        return response()->json([
            'message' => $falla->fll_estado == 1 ? 'Falla activada correctamente' : 'Falla desactivada correctamente',
            'data'    => new FallaResource($falla),
        ], 200);
    }
}
