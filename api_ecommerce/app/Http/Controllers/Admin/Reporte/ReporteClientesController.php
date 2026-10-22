<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteClientesRequest;
use App\Services\Reporte\ClientesReporteService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Http\JsonResponse;

/**
 * Reporte de clientes. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteClientesController extends Controller
{
    public function __construct(private readonly ClientesReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/clientes/top?limite=20&tipo_cliente_id= */
    public function top(ReporteClientesRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->top($f, $request->tipoClienteId()), $f);
    }

    /** GET /api/admin/reportes/clientes/nuevos?agrupacion=mes */
    public function nuevos(ReporteClientesRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->nuevos($f), $f);
    }

    /** GET /api/admin/reportes/clientes/inactivos?dias=90&tipo_cliente_id= */
    public function inactivos(ReporteClientesRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder(
            $this->servicio->inactivos($f, $request->dias(), $request->tipoClienteId()), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}
