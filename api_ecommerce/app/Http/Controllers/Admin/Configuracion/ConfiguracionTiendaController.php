<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfiguracionTiendaRequest;
use App\Http\Resources\Configuracion\ConfiguracionTiendaResource;
use App\Models\Configuracion\ConfiguracionTienda;
use Illuminate\Http\JsonResponse;

class ConfiguracionTiendaController extends Controller
{
    // GET /api/admin/configuracion-tienda
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new ConfiguracionTiendaResource(ConfiguracionTienda::actual()),
        ], 200);
    }

    /**
     * PATCH /api/admin/configuracion-tienda
     *
     * Singleton: no hay id en la ruta ni store/destroy. Los cambios se ven
     * en la tienda en el siguiente request del Detalle del Producto (no hay
     * nada que recalcular ni cachear).
     */
    public function update(ConfiguracionTiendaRequest $request): JsonResponse
    {
        $config = ConfiguracionTienda::actual();
        $config->fill($request->validated());
        $config->save();

        return response()->json([
            'message' => 'Configuración de la tienda actualizada correctamente.',
            'data'    => new ConfiguracionTiendaResource($config),
        ], 200);
    }
}