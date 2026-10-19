<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\TipoDispositivoRequest;
use App\Http\Resources\Servicio\TipoDispositivoResource;
use App\Models\Servicio\TipoDispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoDispositivoController extends Controller
{
    /** Tablas que referencian a tipo_dispositivo. hasTable() cubre las que se crean en pasos siguientes. */
    private const DEPENDENCIAS = [
        'falla'              => ['columna' => 'fll_tdiid', 'etiqueta' => 'falla(s)'],
        'solicitud_servicio' => ['columna' => 'sse_tdiid', 'etiqueta' => 'solicitud(es) de servicio'],
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

    // GET /api/admin/tipos-dispositivo?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoDispositivo::query();

        if ($request->filled('search')) {
            $search = '%' . mb_strtolower($request->search) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(tdi_codigo) LIKE ?', [$search])
                                       ->orWhereRaw('LOWER(tdi_descri) LIKE ?', [$search]));
        }
        if ($request->filled('estado')) {
            $query->where('tdi_estado', $request->estado);
        }

        $tipos = $query->orderBy('tdi_descri')->get();
        $usos  = $this->contarUsos();
        $tipos->each(fn ($t) => $t->usos_count = array_sum($usos[$t->tdi_id] ?? []));

        return response()->json(['data' => TipoDispositivoResource::collection($tipos)], 200);
    }

    // GET /api/admin/tipos-dispositivo/select
    public function select(): JsonResponse
    {
        $tipos = TipoDispositivo::activos()->orderBy('tdi_descri')->get(['tdi_id', 'tdi_codigo', 'tdi_descri']);

        return response()->json(['data' => $tipos], 200);
    }

    public function show(int $id): JsonResponse
    {
        $tipo = TipoDispositivo::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de dispositivo no encontrado'], 404);

        return response()->json(['data' => new TipoDispositivoResource($tipo)], 200);
    }

    public function store(TipoDispositivoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['tdi_estado'] = $datos['tdi_estado'] ?? 1;

        $tipo = TipoDispositivo::create($datos);

        return response()->json([
            'message' => 'Tipo de dispositivo registrado correctamente',
            'data'    => new TipoDispositivoResource($tipo->fresh()),
        ], 201);
    }

    public function update(TipoDispositivoRequest $request, int $id): JsonResponse
    {
        $tipo = TipoDispositivo::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de dispositivo no encontrado'], 404);

        $tipo->update($request->validated());

        return response()->json([
            'message' => 'Tipo de dispositivo actualizado correctamente',
            'data'    => new TipoDispositivoResource($tipo->fresh()),
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoDispositivo::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de dispositivo no encontrado'], 404);

        $detalle = $this->detalleUsos($id);
        if ($detalle) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de dispositivo está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de dispositivo eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-dispositivo/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoDispositivo::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de dispositivo no encontrado'], 404);

        $tipo->tdi_estado = $tipo->tdi_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tdi_estado == 1
                ? 'Tipo de dispositivo activado correctamente'
                : 'Tipo de dispositivo desactivado correctamente',
            'data'    => new TipoDispositivoResource($tipo),
        ], 200);
    }
}
