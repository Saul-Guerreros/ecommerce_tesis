<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\EntregaServicioRequest;
use App\Http\Requests\Servicio\ReasignarTecnicoRequest;
use App\Http\Requests\Servicio\RegistrarContactoRequest;
use App\Http\Requests\Servicio\RespuestaPresupuestoRequest;
use App\Http\Requests\Servicio\SolicitudServicioIndexRequest;
use App\Http\Resources\Servicio\PanelSolicitudResource;
use App\Models\Configuracion\Sucursal;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\ConstanciaServicioService;
use App\Services\Servicio\EntregaServicioService;
use App\Services\Servicio\PanelServicioService;
use App\Services\Servicio\PresupuestoPdfService;
use App\Services\Servicio\PresupuestoService;
use App\Services\Servicio\ServicioEstadoService;
use Illuminate\Support\Facades\DB;
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
        private PresupuestoService $presupuestos,
        private PresupuestoPdfService $pdfPresupuestos,
        private ServicioEstadoService $estados,
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
            'sucursales' => Sucursal::where('suc_estado', 1)->orderBy('suc_nombre')->get(['suc_id', 'suc_nombre']),
            'canales'    => [SolicitudServicio::CANAL_WEB, SolicitudServicio::CANAL_MOSTRADOR],
            'contacto_alerta_dias' => (int) config('servicio.contacto_alerta_dias'),
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
                'recepcion.tecnico.persona', 'ultimoContacto',
                // enlace de WhatsApp de las filas con presupuesto ENVIADO (sin N+1)
                'cliente.persona.contactos', 'cliente.persona.usuario:usu_id,usu_perid',
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

    // ---------- Paso 2: aprobación asistida y contacto con el cliente ----------

    // POST /api/admin/servicios/panel/{id}/presupuesto/respuesta
    public function responderPresupuesto(RespuestaPresupuestoRequest $request, int $id): JsonResponse
    {
        $d = $request->validated();
        $s = SolicitudServicio::with(['estadoServicio', 'ordenServicio.presupuestoActual.estado'])->findOrFail($id);
        $usuario = auth('api')->user();

        $p = $d['decision'] === 'APROBAR'
            ? $this->presupuestos->aprobarAsistido($s, $d['canal'], $d['obs'], $usuario)
            : $this->presupuestos->rechazarAsistido($s, $d['canal'], $d['motivo'], $d['obs'], $usuario);

        return response()->json([
            'message' => "Presupuesto {$p->pse_numero} " . ($d['decision'] === 'APROBAR' ? 'aprobado' : 'rechazado') . ' correctamente',
        ], 200);
    }

    // POST /api/admin/servicios/panel/{id}/contacto
    public function registrarContacto(RegistrarContactoRequest $request, int $id): JsonResponse
    {
        $s = SolicitudServicio::with('estadoServicio')->findOrFail($id);
        $d = $request->validated();

        DB::transaction(fn () => $this->estados->registrarContacto(
            $s, auth('api')->user()->usu_id, $d['medio'], $d['resultado'], $d['obs'] ?? null
        ));

        return response()->json(['message' => 'Contacto registrado'], 201);
    }

    // GET /api/admin/servicios/panel/{id}/presupuesto/pdf
    public function pdfPresupuesto(int $id): Response
    {
        return $this->pdfPresupuestos->generar(SolicitudServicio::findOrFail($id));
    }

    private function cargarParaEntrega(int $id): SolicitudServicio
    {
        return SolicitudServicio::with([
            'cliente.persona.contactos', 'tipoDispositivo', 'tipoEntrega', 'estadoServicio', 'estadoPago', 'estadoEgreso',
            'recepcion.accesorios', 'ordenServicio',
        ])->findOrFail($id);
    }
}
