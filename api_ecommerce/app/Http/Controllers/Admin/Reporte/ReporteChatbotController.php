<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteChatbotRequest;
use App\Services\Reporte\ChatbotReporteService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Http\JsonResponse;

/**
 * Reporte del asistente virtual. Respuesta: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteChatbotController extends Controller
{
    public function __construct(private readonly ChatbotReporteService $servicio)
    {
    }

    /** GET /api/admin/reportes/chatbot/periodo?agrupacion=dia|mes|anio */
    public function periodo(ReporteChatbotRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->porPeriodo($f), $f);
    }

    /** GET /api/admin/reportes/chatbot/temas */
    public function temas(ReporteChatbotRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->temas($f), $f);
    }

    /** GET /api/admin/reportes/chatbot/preguntas?limite=20 */
    public function preguntas(ReporteChatbotRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->preguntas($f), $f);
    }

    /** GET /api/admin/reportes/chatbot/sin-respuesta?tipo=todos|sin_respuesta|derivada */
    public function sinRespuesta(ReporteChatbotRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->sinRespuesta($f, $request->tipo()), $f);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}
