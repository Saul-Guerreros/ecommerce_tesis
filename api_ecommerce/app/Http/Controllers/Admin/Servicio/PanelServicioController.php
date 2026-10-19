<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\EntregaServicioRequest;
use App\Http\Requests\Servicio\ReasignarTecnicoRequest;
use App\Http\Requests\Servicio\SolicitudServicioIndexRequest;
use App\Http\Resources\Servicio\PanelSolicitudResource;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\ConstanciaServicioService;
use App\Services\Servicio\EntregaServicioService;
use App\Services\Servicio\PanelServicioService;
use App\Services\Servicio\ReasignacionService;
use App\Services\Servicio\ServicioConsultaService;
use App\Services\Servicio\TecnicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A3 - Panel general (RF-040) + A5 entrega (RF-039) + constancias PDF.
 */
class PanelServicioController extends Controller
{
    public function __construct(
        private PanelServicioService $panel,
        private ServicioConsultaService $consulta,
        private ReasignacionService $reasignacion,
        private EntregaServicioService $entregas,
        private ConstanciaServicioService $constancias,
        private TecnicoService $tecnicos,
    ) {}

    // GET /api/admin/servicios/panel/resumen
    public function resumen(): JsonResponse
    {
        return response()->json(['data' => $this->panel->resumen()], 200);
    }

    // GET /api/admin/servicios/panel/config
    public function config(): JsonResponse
    {
        return response()->json([
            'estados_servicio' => EstadoServicio::where('ets_estado', 1)->orderBy('ets_orden')
                ->get(['ets_id', 'ets_codigo', 'ets_descri']),
            'tecnicos' => $this->tecnicos->activos(),
        ], 200);
    }

    // GET /api/admin/servicios/panel?buscar=&ets_codigo=&tec_id=&solo_activas=1&page=
    public function index(SolicitudServicioIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated() + [
            'solo_activas' => $request->boolean('solo_activas', true),
            'ets_codigo'   => $request->input('ets_codigo'),
            'tec_id'       => $request->input('tec_id'),
        ];

        $pagina = $this->consulta->query($filtros)
            ->with([
                'estadoPago', 'estadoEgreso', 'entrega:ese_id,ese_sseid',
                'ordenServicio:ose_id,ose_sseid,ose_numero', 'ordenServicio.presupuestoActual.estado',
                'recepcion.tecnico.persona',
            ])
            ->paginate((int) ($filtros['per_page'] ?? 20));

        return response()->json([
            'data' => PanelSolicitudResource::collection($pagina->items()),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'desde'        => $pagina->firstItem() ?? 0,
                'hasta'        => $pagina->lastItem() ?? 0,
            ],
        ], 200);
    }

    // POST /api/admin/servicios/panel/{id}/reasignar
    public function reasignar(ReasignarTecnicoRequest $request, int $id): JsonResponse
    {
        $s = SolicitudServicio::with(['estadoServicio', 'recepcion', 'ordenServicio'])->findOrFail($id);

        $this->reasignacion->reasignar($s, (int) $request->validated('rse_tecid'), $request->validated('motivo'), auth('api')->user());

        return response()->json(['message' => 'Técnico reasignado correctamente'], 200);
    }

    // GET /api/admin/servicios/panel/{id}/entrega   — datos del modal A5
    public function entregaConfig(int $id): JsonResponse
    {
        $s = $this->cargarParaEntrega($id);

        return response()->json(['data' => $this->entregas->configuracion($s) + [
            'sse_numero' => $s->sse_numero,
            'cliente'    => $s->cliente?->persona?->nombre_completo,
            'equipo'     => trim(($s->tipoDispositivo?->tdi_descri ?? '') . ' ' . $s->sse_marca . ' ' . $s->sse_modelo),
            'ingreso_a_domicilio' => $s->esADomicilio(),
            'sse_dirid'  => $s->sse_dirid,
            'sse_sucid'  => $s->sse_sucid,
        ]], 200);
    }

    // POST /api/admin/servicios/panel/{id}/entrega
    public function entregar(EntregaServicioRequest $request, int $id): JsonResponse
    {
        $this->entregas->registrar($this->cargarParaEntrega($id), $request->validated(), auth('api')->user());

        return response()->json(['message' => 'Entrega registrada. Puede imprimir la constancia de retiro.'], 201);
    }

    // GET /api/admin/servicios/panel/{id}/constancia/{tipo}   (tipo: recepcion | retiro)
    public function constancia(int $id, string $tipo): Response
    {
        return $this->constancias->generar(SolicitudServicio::findOrFail($id), $tipo);
    }

    private function cargarParaEntrega(int $id): SolicitudServicio
    {
        return SolicitudServicio::with([
            'cliente.persona.contactos', 'tipoDispositivo', 'tipoEntrega', 'estadoServicio', 'estadoPago', 'estadoEgreso',
            'recepcion.accesorios', 'ordenServicio',
        ])->findOrFail($id);
    }
}
