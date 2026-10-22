<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteComprasRequest;
use App\Services\Reporte\ComprasReporteService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de compras. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteComprasController extends Controller
{
    public function __construct(private readonly ComprasReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/compras/proveedores?proveedor_id= */
    public function proveedores(ReporteComprasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porProveedor($f, $request->proveedorId()), $f);
    }

    /** GET /api/admin/reportes/compras/cxp-vencidas?proveedor_id= */
    public function vencidas(ReporteComprasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->cuentasVencidas($f, $request->proveedorId()), $f);
    }

    /** GET /api/admin/reportes/compras/cxp-por-vencer?dias=30&proveedor_id= */
    public function porVencer(ReporteComprasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->cuentasPorVencer($f, $request->dias(), $request->proveedorId()), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}
