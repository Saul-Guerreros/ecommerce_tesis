<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteComportamientoRequest;
use App\Services\Comportamiento\InsightsComportamientoService;
use App\Services\Reporte\ComportamientoReporteService;
use App\Services\Reporte\FiltrosReporte;
use Illuminate\Http\JsonResponse;

/**
 * Análisis de comportamiento de clientes.
 * Respuesta tabular: { data: [filas], meta: { filtros, totales } }.
 */
class ReporteComportamientoController extends Controller
{
    public function __construct(
        private readonly ComportamientoReporteService $servicio,
        private readonly InsightsComportamientoService $insights,
    ) {
    }

    // ---------- A. Búsquedas ----------

    /** GET /api/admin/reportes/comportamiento/busquedas?origen=TIENDA|CHATBOT */
    public function busquedas(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->busquedas($f, $request->origen()), $f);
    }

    /** GET /api/admin/reportes/comportamiento/sin-resultados */
    public function sinResultados(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->sinResultados($f, $request->origen()), $f);
    }

    /** GET /api/admin/reportes/comportamiento/productos-buscados */
    public function productosBuscados(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->productosBuscados($f), $f);
    }

    // ---------- B. Patrones de compra ----------

    /** GET /api/admin/reportes/comportamiento/franja-horaria */
    public function franjaHoraria(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->franjaHoraria($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/comprados-juntos */
    public function compradosJuntos(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->compradosJuntos($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/recompra */
    public function recompra(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->recompra($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/modalidades */
    public function modalidades(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->modalidades($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/embudo */
    public function embudo(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtros();

        return $this->responder($this->servicio->embudo($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/carritos-abandonados?horas=48 */
    public function carritosAbandonados(ReporteComportamientoRequest $request): JsonResponse
    {
        return $this->responder($this->servicio->carritosAbandonados($request->horas()), $request->filtros());
    }

    // ---------- C. Preferencias ----------

    /** GET /api/admin/reportes/comportamiento/segmentos?meses=12 */
    public function segmentos(ReporteComportamientoRequest $request): JsonResponse
    {
        $f = $request->filtrosHistoricos();

        return $this->responder($this->servicio->segmentos($f), $f);
    }

    /** GET /api/admin/reportes/comportamiento/clientes?q=juan */
    public function buscarClientes(ReporteComportamientoRequest $request): JsonResponse
    {
        $request->validate(['q' => ['required']], ['q.required' => 'Escribí un nombre, cédula o RUC.']);

        return response()->json(['data' => $this->servicio->buscarClientes($request->input('q'))]);
    }

    /** GET /api/admin/reportes/comportamiento/clientes/{cliente}?meses=12 */
    public function perfil(ReporteComportamientoRequest $request, int $cliente): JsonResponse
    {
        $f = $request->filtrosHistoricos();

        return response()->json([
            'data' => $this->servicio->perfilCliente($cliente, $f),
            'meta' => ['filtros' => $f->toArray()],
        ]);
    }

    // ---------- Recomendaciones con IA ----------

    /** POST /api/admin/reportes/comportamiento/insights { desde, hasta, cliente_id?, meses?, forzar? } */
    public function generarInsights(ReporteComportamientoRequest $request): JsonResponse
    {
        if (! $this->insights->disponible()) {
            return response()->json(['message' => 'La IA no está configurada en el servidor.'], 503);
        }

        $clienteId = $request->clienteId();
        $f = $clienteId ? $request->filtrosHistoricos() : $request->filtros();
        $resultado = $this->insights->generar($f, $clienteId, $request->forzar());

        if (! $resultado) {
            return response()->json(['message' => 'La IA no respondió. Intentá de nuevo en unos minutos.'], 503);
        }

        return response()->json(['data' => $resultado, 'meta' => ['filtros' => $f->toArray()]]);
    }

    private function responder(array $resultado, FiltrosReporte $f): JsonResponse
    {
        return response()->json([
            'data' => $resultado['filas'],
            'meta' => ['filtros' => $f->toArray(), 'totales' => $resultado['totales']],
        ]);
    }
}