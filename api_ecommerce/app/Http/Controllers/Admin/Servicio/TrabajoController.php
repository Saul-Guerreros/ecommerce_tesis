<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\TrabajoRequest;
use App\Http\Resources\Servicio\TrabajoResource;
use App\Models\Servicio\Trabajo;
use App\Models\TipoIva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrabajoController extends Controller
{
    private const DEPENDENCIAS = [
        'presupuesto_detalle' => ['columna' => 'psd_trbid', 'etiqueta' => 'línea(s) de presupuesto'],
    ];

    private function contarUsos(): array
    {
        $usos = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;

            $conteos = DB::table($tabla)
                ->whereNotNull($info['columna'])
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

    // GET /api/admin/trabajos?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Trabajo::with('tipoIva');

        if ($request->filled('search')) {
            $search = '%' . mb_strtolower($request->search) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(trb_codigo) LIKE ?', [$search])
                                       ->orWhereRaw('LOWER(trb_descri) LIKE ?', [$search]));
        }
        if ($request->filled('estado')) {
            $query->where('trb_estado', $request->estado);
        }

        $trabajos = $query->orderBy('trb_descri')->get();
        $usos     = $this->contarUsos();
        $trabajos->each(fn ($t) => $t->usos_count = array_sum($usos[$t->trb_id] ?? []));

        return response()->json(['data' => TrabajoResource::collection($trabajos)], 200);
    }

    // GET /api/admin/trabajos/config
    public function config(): JsonResponse
    {
        return response()->json([
            'tipos_iva' => TipoIva::where('tiv_estado', 1)->orderBy('tiv_tasa')
                ->get(['tiv_id', 'tiv_descri', 'tiv_tasa']),
        ], 200);
    }

    // GET /api/admin/trabajos/select  — lo usará el formulario de presupuesto (T3)
    public function select(): JsonResponse
    {
        $trabajos = Trabajo::activos()->with('tipoIva')->orderBy('trb_descri')->get();

        return response()->json(['data' => TrabajoResource::collection($trabajos)], 200);
    }

    public function show(int $id): JsonResponse
    {
        $trabajo = Trabajo::with('tipoIva')->find($id);
        if (!$trabajo) return response()->json(['message' => 'Trabajo no encontrado'], 404);

        return response()->json(['data' => new TrabajoResource($trabajo)], 200);
    }

    public function store(TrabajoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['trb_estado'] = $datos['trb_estado'] ?? 1;

        $trabajo = Trabajo::create($datos);

        return response()->json([
            'message' => 'Trabajo registrado correctamente',
            'data'    => new TrabajoResource($trabajo->fresh('tipoIva')),
        ], 201);
    }

    /** Cambiar el precio NO altera presupuestos emitidos: cada línea guarda su propio psd_precio. */
    public function update(TrabajoRequest $request, int $id): JsonResponse
    {
        $trabajo = Trabajo::find($id);
        if (!$trabajo) return response()->json(['message' => 'Trabajo no encontrado'], 404);

        $trabajo->update($request->validated());

        return response()->json([
            'message' => 'Trabajo actualizado correctamente',
            'data'    => new TrabajoResource($trabajo->fresh('tipoIva')),
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $trabajo = Trabajo::find($id);
        if (!$trabajo) return response()->json(['message' => 'Trabajo no encontrado'], 404);

        $detalle = $this->detalleUsos($id);
        if ($detalle) {
            return response()->json([
                'message' => 'No se puede eliminar: el trabajo figura en '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $trabajo->delete();

        return response()->json(['message' => 'Trabajo eliminado correctamente'], 200);
    }

    public function toggleEstado(int $id): JsonResponse
    {
        $trabajo = Trabajo::with('tipoIva')->find($id);
        if (!$trabajo) return response()->json(['message' => 'Trabajo no encontrado'], 404);

        $trabajo->trb_estado = $trabajo->trb_estado == 1 ? 0 : 1;
        $trabajo->save();

        return response()->json([
            'message' => $trabajo->trb_estado == 1 ? 'Trabajo activado correctamente' : 'Trabajo desactivado correctamente',
            'data'    => new TrabajoResource($trabajo),
        ], 200);
    }
}
