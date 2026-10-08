<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfiguracionEnvioRequest;
use App\Http\Resources\Configuracion\ConfiguracionEnvioResource;
use App\Models\Configuracion\ConfiguracionEnvio;
use Illuminate\Http\JsonResponse;

class ConfiguracionEnvioController extends Controller
{
    // GET /api/admin/configuracion-envio
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new ConfiguracionEnvioResource(ConfiguracionEnvio::actual()),
        ], 200);
    }

    // PATCH /api/admin/configuracion-envio (singleton: sin id, sin store/destroy)
    public function update(ConfiguracionEnvioRequest $request): JsonResponse
    {
        $config = ConfiguracionEnvio::actual();
        $config->fill($request->validated());
        $config->cfe_actualizado = now();
        $config->save();

        return response()->json([
            'message' => 'Configuración de envío actualizada correctamente.',
            'data'    => new ConfiguracionEnvioResource($config),
        ], 200);
    }
}