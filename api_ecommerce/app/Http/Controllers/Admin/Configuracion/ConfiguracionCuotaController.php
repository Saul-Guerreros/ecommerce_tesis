<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrito\ConfiguracionCuotaRequest;
use App\Http\Resources\Carrito\ConfiguracionCuotaResource;
use App\Http\Resources\Carrito\ProductoCuotaResource;
use App\Models\Carrito\ConfiguracionCuota;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionCuotaController extends Controller
{
    // GET /api/admin/configuraciones-cuota/productos?search=&solo_con_cuotas=1
    public function productos(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->where('prd_estado', 1)
            ->withCount([
                'configuracionesCuota as cuotas_total',
                'configuracionesCuota as cuotas_activas' => fn ($q) => $q->where('cfc_estado', true),
            ]);

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->search).'%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(prd_descri) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(prd_codinv) LIKE ?', [$search]);
            });
        }

        if ($request->boolean('solo_con_cuotas')) {
            $query->has('configuracionesCuota');
        }

        $productos = $query->orderBy('prd_descri')->get();

        return response()->json(['data' => ProductoCuotaResource::collection($productos)], 200);
    }

    // GET /api/admin/configuraciones-cuota?producto=<prd_id>  (obligatorio)
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'producto' => ['required', 'integer', 'exists:producto,prd_id'],
        ]);

        $configuraciones = ConfiguracionCuota::with('producto')
            ->where('cfc_prdid', $request->producto)
            ->orderBy('cfc_cantidad')
            ->get();

        return response()->json([
            'data' => ConfiguracionCuotaResource::collection($configuraciones),
        ], 200);
    }

    // POST /api/admin/configuraciones-cuota
    public function store(ConfiguracionCuotaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['cfc_estado'] = $datos['cfc_estado'] ?? true;

        $configuracion = ConfiguracionCuota::create($datos);
        $configuracion->load('producto');

        return response()->json([
            'message' => 'Opción de financiación registrada correctamente.',
            'data'    => new ConfiguracionCuotaResource($configuracion),
        ], 201);
    }

    // PUT /api/admin/configuraciones-cuota/{id}
    public function update(ConfiguracionCuotaRequest $request, int $id): JsonResponse
    {
        $configuracion = ConfiguracionCuota::find($id);

        if (! $configuracion) {
            return response()->json(['message' => 'Configuración no encontrada.'], 404);
        }

        $configuracion->update($request->validated());
        $configuracion->load('producto');

        return response()->json([
            'message' => 'Opción de financiación actualizada correctamente.',
            'data'    => new ConfiguracionCuotaResource($configuracion),
        ], 200);
    }

    // DELETE /api/admin/configuraciones-cuota/{id}
    // Sin 409: el carrito guarda una FOTO del recargo/monto, no una FK.
    public function destroy(int $id): JsonResponse
    {
        $configuracion = ConfiguracionCuota::find($id);

        if (! $configuracion) {
            return response()->json(['message' => 'Configuración no encontrada.'], 404);
        }

        $configuracion->delete();

        return response()->json(['message' => 'Opción de financiación eliminada correctamente.'], 200);
    }

    // PATCH /api/admin/configuraciones-cuota/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $configuracion = ConfiguracionCuota::find($id);

        if (! $configuracion) {
            return response()->json(['message' => 'Configuración no encontrada.'], 404);
        }

        $configuracion->cfc_estado = ! $configuracion->cfc_estado;
        $configuracion->save();
        $configuracion->load('producto');

        return response()->json([
            'message' => $configuracion->cfc_estado
                ? 'Opción activada correctamente.'
                : 'Opción desactivada correctamente.',
            'data' => new ConfiguracionCuotaResource($configuracion),
        ], 200);
    }
}