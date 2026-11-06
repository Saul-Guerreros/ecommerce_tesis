<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteInventarioRequest;
use App\Services\Reporte\FiltrosReporte;
use App\Services\Reporte\InventarioReporteService;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de inventario. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteInventarioController extends Controller
{
    public function __construct(private readonly InventarioReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/inventario/valorizado */
    public function valorizado(ReporteInventarioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->valorizado($f, $request->categoriaId(), $request->marcaId()), $f);
    }

    /** GET /api/admin/reportes/inventario/critico */
    public function critico(ReporteInventarioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->critico($f, $request->categoriaId(), $request->marcaId()), $f);
    }

    /** GET /api/admin/reportes/inventario/sin-movimiento?dias=90 */
    public function sinMovimiento(ReporteInventarioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->sinMovimiento($f, $request->dias(), $request->categoriaId(), $request->marcaId()), $f);
    }

    /** GET /api/admin/reportes/inventario/margen */
    public function margen(ReporteInventarioRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->margen($f, $request->categoriaId(), $request->marcaId()), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}