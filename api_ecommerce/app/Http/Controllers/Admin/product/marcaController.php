<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Requests\product\MarcaRequest;
use App\Http\Resources\product\MarcaResource;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class marcaController extends Controller
{
    private const DEPENDENCIAS = [
        'producto' => ['columna' => 'prd_marid', 'etiqueta' => 'producto(s)'],
    ];

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

            foreach ($conteos as $marId => $total) {
                $usos[$marId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    /**
     * GET /api/marcas?search=&estado=
     * Se mantiene en la raíz (fuera del grupo admin) por compatibilidad con
     * los consumidores que ya la llaman así: catalogo.service.ts,
     * Producto, Cupones y Discounts. Devuelve TODAS las marcas (activas e
     * inactivas) igual que antes, cuando el concepto de estado no existía.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Marca::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(mar_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('mar_estado', $request->estado);
        }

        $marcas = $query->orderBy('mar_descri')->get();
        $usos   = $this->contarUsos();

        $marcas->each(function ($m) use ($usos) {
            $m->usos_count = array_sum($usos[$m->mar_id] ?? []);
        });

        return response()->json(['data' => MarcaResource::collection($marcas)], 200);
    }

    /**
     * GET /api/marcas/select
     * Lista liviana, solo marcas activas. Pensada para combos nuevos
     * (ej. cuando se reescriba Cupones/Discounts). Los combos legacy
     * siguen usando index() por ahora, sin cambios de comportamiento.
     */
    public function select(): JsonResponse
    {
        $marcas = Marca::where('mar_estado', 1)
            ->orderBy('mar_descri')
            ->get(['mar_id', 'mar_descri']);

        return response()->json(['data' => $marcas], 200);
    }

    // GET /api/marcas/{marca}
    public function show(int $id): JsonResponse
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        return response()->json(['data' => new MarcaResource($marca)], 200);
    }

    // POST /api/marcas
    public function store(MarcaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['mar_estado'] = $datos['mar_estado'] ?? 1;

        $marca = Marca::create($datos);

        return response()->json([
            'message' => 'Marca registrada correctamente',
            'data'    => new MarcaResource($marca),
        ], 201);
    }

    // PUT /api/marcas/{marca}
    public function update(MarcaRequest $request, int $id): JsonResponse
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $marca->update($request->validated());

        return response()->json([
            'message' => 'Marca actualizada correctamente',
            'data'    => new MarcaResource($marca->fresh()),
        ], 200);
    }

    // DELETE /api/marcas/{marca}
    public function destroy(int $id): JsonResponse
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
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
                'message' => 'No se puede eliminar: la marca está siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
            ], 409);
        }

        $marca->delete();

        return response()->json(['message' => 'Marca eliminada correctamente'], 200);
    }

    // PATCH /api/marcas/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $marca->mar_estado = $marca->mar_estado == 1 ? 0 : 1;
        $marca->save();

        return response()->json([
            'message' => $marca->mar_estado == 1
                ? 'Marca activada correctamente'
                : 'Marca desactivada correctamente',
            'data'    => new MarcaResource($marca),
        ], 200);
    }
}