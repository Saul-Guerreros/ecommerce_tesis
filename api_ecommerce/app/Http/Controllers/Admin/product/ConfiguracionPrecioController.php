<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Controllers\Controller;
use App\Http\Requests\product\ConfiguracionPrecioRequest;
use App\Http\Resources\product\ConfiguracionPrecioResource;
use App\Models\ConfiguracionPrecio;
use App\Services\Precio\PriceService;
use Illuminate\Http\JsonResponse;

class ConfiguracionPrecioController extends Controller
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

    // GET /api/admin/configuracion-precio
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new ConfiguracionPrecioResource(ConfiguracionPrecio::actual()),
        ], 200);
    }

    /**
     * PATCH /api/admin/configuracion-precio
     *
     * Al guardar, recalcula el precio de TODOS los productos que usan el
     * margen/umbral general (los que tienen override individual no se
     * tocan). Es la operación más costosa del módulo — aceptable acá
     * porque solo la ejecuta un administrador, de forma manual y
     * ocasional (no en cada request de catálogo).
     */
    public function update(ConfiguracionPrecioRequest $request): JsonResponse
    {
        $config = ConfiguracionPrecio::actual();
        $config->fill($request->validated());
        $config->save();

        $this->priceService->recalcularProductosConMargenGeneral();

        return response()->json([
            'message' => 'Configuración general de precios actualizada correctamente.',
            'data'    => new ConfiguracionPrecioResource($config),
        ], 200);
    }
}