<?php

namespace App\Http\Controllers\Admin\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\TipoClienteRequest;
use App\Http\Resources\Cliente\TipoClienteResource;
use App\Models\TipoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoClienteController extends Controller
{
    /** Tablas que referencian a tipo_cliente (para "en uso" y bloqueo de borrado). */
    private const DEPENDENCIAS = [
        'cliente' => ['columna' => 'cli_tpcid', 'etiqueta' => 'cliente(s)'],
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

    // GET /api/admin/tipos-cliente?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoCliente::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(tpc_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado')) {
            $query->where('tpc_estado', $request->estado);
        }

        $tipos = $query->orderBy('tpc_descri')->get();
        $usos  = $this->contarUsos();

        $tipos->each(function ($t) use ($usos) {
            $t->usos_count = array_sum($usos[$t->tpc_id] ?? []);
        });

        return response()->json(['data' => TipoClienteResource::collection($tipos)], 200);
    }

    // GET /api/admin/tipos-cliente/select
    public function select(): JsonResponse
    {
        $tipos = TipoCliente::where('tpc_estado', 1)
            ->orderBy('tpc_descri')
            ->get(['tpc_id', 'tpc_descri']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-cliente/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoCliente::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de cliente no encontrado'], 404);

        return response()->json(['data' => new TipoClienteResource($tipo)], 200);
    }

    // POST /api/admin/tipos-cliente
    public function store(TipoClienteRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['tpc_estado'] = $datos['tpc_estado'] ?? 1;

        $tipo = TipoCliente::create($datos);

        return response()->json([
            'message' => 'Tipo de cliente registrado correctamente',
            'data'    => new TipoClienteResource($tipo->fresh()),
        ], 201);
    }

    // PUT /api/admin/tipos-cliente/{id}
    public function update(TipoClienteRequest $request, int $id): JsonResponse
    {
        $tipo = TipoCliente::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de cliente no encontrado'], 404);

        $tipo->update($request->validated());

        return response()->json([
            'message' => 'Tipo de cliente actualizado correctamente',
            'data'    => new TipoClienteResource($tipo->fresh()),
        ], 200);
    }

    // DELETE /api/admin/tipos-cliente/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoCliente::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de cliente no encontrado'], 404);

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de cliente está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de cliente eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-cliente/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoCliente::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de cliente no encontrado'], 404);

        $tipo->tpc_estado = $tipo->tpc_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tpc_estado == 1
                ? 'Tipo de cliente activado correctamente'
                : 'Tipo de cliente desactivado correctamente',
            'data'    => new TipoClienteResource($tipo),
        ], 200);
    }
}
