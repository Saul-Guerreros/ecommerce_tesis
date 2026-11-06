<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\FiltroReporteRequest;
use App\Services\Reporte\FiltrosReporte;
use App\Services\Reporte\PromocionesReporteService;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de promociones (configuración de cupones y campañas).
 * Usa el FiltroReporteRequest base: no tiene filtros propios.
 */
class ReportePromocionesController extends Controller
{
    public function __construct(private readonly PromocionesReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/promociones/cupones */
    public function cupones(FiltroReporteRequest $request): JsonResponse
    {
        return $this->responder($this->servicio->cupones(), $request->filtros());
    }

    /** GET /api/admin/reportes/promociones/campanas?desde=&hasta= */
    public function campanas(FiltroReporteRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->campanas($f), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}
