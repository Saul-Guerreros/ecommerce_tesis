<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\FiltroReporteRequest;
use App\Services\Reporte\DashboardService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Http\JsonResponse;

/**
 * Métricas del dashboard. Solo recibe filtros validados y responde:
 * la lógica de negocio está en DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $servicio)
    {
    }

    /** GET /api/admin/metricas/dashboard/resumen */
    public function resumen(FiltroReporteRequest $request): JsonResponse
    {
        $filtros = $request->filtros();

        return $this->responder($this->servicio->resumen($filtros), $filtros);
    }

    /** GET /api/admin/metricas/dashboard/ventas-compras?agrupacion=dia|mes|anio */
    public function ventasCompras(FiltroReporteRequest $request): JsonResponse
    {
        $filtros = $request->filtros(rangoPorAgrupacion: true);

        return $this->responder($this->servicio->ventasCompras($filtros), $filtros);
    }

    /** GET /api/admin/metricas/dashboard/distribucion */
    public function distribucion(FiltroReporteRequest $request): JsonResponse
    {
        $filtros = $request->filtros();

        return $this->responder($this->servicio->distribucion($filtros), $filtros);
    }

    /** GET /api/admin/metricas/dashboard/tablas */
    public function tablas(FiltroReporteRequest $request): JsonResponse
    {
        $filtros = $request->filtros();

        return $this->responder($this->servicio->tablas($filtros), $filtros);
    }

    private function responder(array $data, FiltrosReporte $filtros): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['filtros' => $filtros->toArray()],
        ]);
    }
}