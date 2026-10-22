<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteServicioRequest;
use App\Services\Reporte\FiltrosReporte;
use App\Services\Reporte\ServicioReporteService;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de servicio técnico. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteServicioController extends Controller
{
    public function __construct(private readonly ServicioReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/servicio/estados */
    public function estados(ReporteServicioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porEstado($f, $request->tipoDispositivoId()), $f);
    }

    /** GET /api/admin/reportes/servicio/tecnicos */
    public function tecnicos(ReporteServicioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porTecnico($f, $request->tipoDispositivoId()), $f);
    }

    /** GET /api/admin/reportes/servicio/tiempos */
    public function tiempos(ReporteServicioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->tiempos($f, $request->tipoDispositivoId()), $f);
    }

    /** GET /api/admin/reportes/servicio/fallas */
    public function fallas(ReporteServicioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->fallas($f, $request->tipoDispositivoId()), $f);
    }

    /** GET /api/admin/reportes/servicio/presupuestos */
    public function presupuestos(ReporteServicioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->presupuestos($f, $request->tipoDispositivoId()), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}
