<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\AgendarRetiroRequest;
use App\Http\Requests\Servicio\ConfirmarRecepcionRequest;
use App\Http\Requests\Servicio\RechazarSolicitudRequest;
use App\Http\Requests\Servicio\SolicitudServicioIndexRequest;
use App\Http\Resources\Servicio\SolicitudServicioListaResource;
use App\Http\Resources\Servicio\SolicitudServicioResource;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\TipoEntrega;
use App\Models\Servicio\EstadoLogistica;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\RecepcionServicioService;
use App\Services\Servicio\ServicioConsultaService;
use App\Services\Servicio\TecnicoService;
use Illuminate\Http\JsonResponse;

/**
 * Bandeja de solicitudes (A1) y recepción (A2). Módulo transaccional:
 * el controlador no persiste, delega en RecepcionServicioService.
 */
class SolicitudServicioController extends Controller
{
    public function __construct(
        private ServicioConsultaService $consulta,
        private RecepcionServicioService $recepcion,
        private TecnicoService $tecnicos,
    ) {}

    // GET /api/admin/servicios/solicitudes
    public function index(SolicitudServicioIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $pagina  = $this->consulta->query($filtros)->paginate((int) ($filtros['per_page'] ?? 20));

        return response()->json([
            'data' => SolicitudServicioListaResource::collection($pagina->items()),
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

    // GET /api/admin/servicios/solicitudes/config
    public function config(): JsonResponse
    {
        return response()->json([
            'estados_servicio' => EstadoServicio::where('ets_estado', 1)->orderBy('ets_orden')
                ->get(['ets_id', 'ets_codigo', 'ets_descri']),
            'estados_ingreso'  => EstadoLogistica::ingreso()->where('elg_estado', 1)->orderBy('elg_id')
                ->get(['elg_id', 'elg_codigo', 'elg_descri']),
            'tipos_entrega'    => TipoEntrega::where('ten_estado', 1)->orderBy('ten_id')
                ->get(['ten_id', 'ten_codigo', 'ten_descri']),
            // Cada técnico trae sus 'sucursales': A2 filtra el combo por la sucursal de la solicitud.
            'tecnicos'         => $this->tecnicos->activos(),
            'sucursales'       => Sucursal::where('suc_estado', 1)->orderBy('suc_nombre')->get(['suc_id', 'suc_nombre']),
            'canales'          => [SolicitudServicio::CANAL_WEB, SolicitudServicio::CANAL_MOSTRADOR],
        ], 200);
    }

    // GET /api/admin/servicios/solicitudes/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => new SolicitudServicioResource($this->cargar($id))], 200);
    }

    // POST /api/admin/servicios/solicitudes/{id}/agendar-retiro
    public function agendarRetiro(AgendarRetiroRequest $request, int $id): JsonResponse
    {
        $s = $this->recepcion->agendarRetiro(
            $this->cargar($id), $request->validated('sse_fecing'), $request->validated('coment'), auth('api')->user()
        );

        return $this->respuesta('Retiro agendado correctamente', $s->sse_id);
    }

    // POST /api/admin/servicios/solicitudes/{id}/en-transito
    public function enTransito(int $id): JsonResponse
    {
        $s = $this->recepcion->marcarEnTransito($this->cargar($id), auth('api')->user());

        return $this->respuesta('Equipo marcado en tránsito', $s->sse_id);
    }

    // POST /api/admin/servicios/solicitudes/{id}/rechazar
    public function rechazar(RechazarSolicitudRequest $request, int $id): JsonResponse
    {
        $s = $this->recepcion->rechazar($this->cargar($id), $request->validated('motivo'), auth('api')->user());

        return $this->respuesta('Solicitud rechazada', $s->sse_id);
    }

    // POST /api/admin/servicios/solicitudes/{id}/recepcion   (multipart/form-data)
    public function confirmarRecepcion(ConfirmarRecepcionRequest $request, int $id): JsonResponse
    {
        $s = $this->recepcion->confirmarRecepcion(
            $this->cargar($id), $request->validated(), $request->file('fotos', []), auth('api')->user()
        );

        return $this->respuesta("Recepción de {$s->sse_numero} registrada correctamente", $s->sse_id);
    }

    // ---------- Auxiliares ----------

    /** Eager loading acorde a lo que espera SolicitudServicioResource (evita N+1). */
    private function cargar(int $id): SolicitudServicio
    {
        return SolicitudServicio::with([
            'cliente.persona.contactos', 'cliente.persona.usuario:usu_id,usu_perid', 'registradoPor.persona', 'tipoDispositivo', 'tipoEntrega', 'pedido:ped_id,ped_numero',
            'sucursal', 'direccion.ciudad', 'estadoServicio', 'estadoIngreso',
            'adjuntos', 'recepcion.accesorios', 'recepcion.tecnico.persona', 'recepcion.recibidoPor.persona',
            'historial.usuario.persona',
            // Paso 2: presupuesto vigente, quién registró la respuesta y último contacto
            'ordenServicio.presupuestoActual.estado', 'ordenServicio.presupuestoActual.respondidoPor.persona',
            'ultimoContacto',
            // Paso 3: garantía del trabajo y reclamos (botón "Registrar reclamo de garantía")
            'ordenServicio', 'entrega', 'reclamos.estadoServicio', 'solicitudOriginal:sse_id,sse_numero',
        ])->findOrFail($id);
    }

    private function respuesta(string $mensaje, int $id): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data'    => new SolicitudServicioResource($this->cargar($id)),
        ], 200);
    }
}