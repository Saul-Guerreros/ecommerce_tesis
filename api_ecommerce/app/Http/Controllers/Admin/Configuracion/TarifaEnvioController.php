<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\TarifaEnvioRequest;
use App\Http\Resources\Configuracion\TarifaEnvioResource;
use App\Models\Configuracion\TarifaEnvio;
use Illuminate\Http\JsonResponse;

class TarifaEnvioController extends Controller
{
    // GET /api/admin/tarifas-envio
    public function index(): JsonResponse
    {
        $tarifas = TarifaEnvio::with(['sucursal', 'ciudad'])
            ->orderBy('tev_sucid')
            ->get();

        return response()->json(['data' => TarifaEnvioResource::collection($tarifas)], 200);
    }

    // POST /api/admin/tarifas-envio
    public function store(TarifaEnvioRequest $request): JsonResponse
    {
        $tarifa = TarifaEnvio::create($request->validated());
        $tarifa->load(['sucursal', 'ciudad']);

        return response()->json([
            'message' => 'Tarifa registrada correctamente',
            'data'    => new TarifaEnvioResource($tarifa),
        ], 201);
    }

    // PUT /api/admin/tarifas-envio/{id}
    public function update(TarifaEnvioRequest $request, int $id): JsonResponse
    {
        $tarifa = TarifaEnvio::find($id);

        if (!$tarifa) {
            return response()->json(['message' => 'Tarifa no encontrada'], 404);
        }

        $tarifa->update($request->validated());
        $tarifa->load(['sucursal', 'ciudad']);

        return response()->json([
            'message' => 'Tarifa actualizada correctamente',
            'data'    => new TarifaEnvioResource($tarifa),
        ], 200);
    }

    // DELETE /api/admin/tarifas-envio/{id}
    public function destroy(int $id): JsonResponse
    {
        $tarifa = TarifaEnvio::find($id);

        if (!$tarifa) {
            return response()->json(['message' => 'Tarifa no encontrada'], 404);
        }

        $tarifa->delete();

        return response()->json(['message' => 'Tarifa eliminada correctamente'], 200);
    }
}