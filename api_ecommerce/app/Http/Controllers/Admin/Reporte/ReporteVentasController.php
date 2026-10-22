<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteVentasRequest;
use App\Services\Reporte\FiltrosReporte;
use App\Services\Reporte\VentasReporteService;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de ventas. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteVentasController extends Controller
{
    public function __construct(private readonly VentasReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/ventas/periodo?agrupacion=dia|mes|anio */
    public function periodo(ReporteVentasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porPeriodo($f), $f);
    }

    /** GET /api/admin/reportes/ventas/productos?categoria_id=&marca_id= */
    public function productos(ReporteVentasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->porProducto($f, $request->categoriaId(), $request->marcaId()), $f);
    }

    /** GET /api/admin/reportes/ventas/agrupado?por=categoria|marca */
    public function agrupado(ReporteVentasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->agrupado($f, $request->por()), $f);
    }

    /** GET /api/admin/reportes/ventas/metodos-pago */
    public function metodosPago(ReporteVentasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porMetodoPago($f), $f);
    }

    /** GET /api/admin/reportes/ventas/ubicacion */
    public function ubicacion(ReporteVentasRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porUbicacion($f), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}