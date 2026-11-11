<?php

namespace App\Http\Controllers\Admin\Credito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credito\CambiarPlanRequest;
use App\Http\Requests\Credito\CronogramaSolicitudRequest;
use App\Http\Requests\Credito\DecisionSolicitudRequest;
use App\Http\Requests\Credito\ObservacionesSolicitudRequest;
use App\Http\Requests\Credito\SolicitudAdminIndexRequest;
use App\Http\Resources\Credito\SolicitudAdminListaResource;
use App\Http\Resources\Credito\SolicitudAdminResource;
use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Usuario;
use App\Services\Credito\GestionSolicitudService;
use Illuminate\Http\JsonResponse;

/**
 * Gestion de Solicitudes de Credito — Panel Admin.
 *
 * Modulo transaccional: el controlador NO persiste. Expone los
 * endpoints, recibe el Request tipado y delega en
 * GestionSolicitudService, que abre la transaccion y registra el
 * historial.
 *
 * No hay destroy(): una solicitud no se borra. La baja es un cambio de
 * estado (RECHAZADA o ANULADA) y queda en la bitacora.
 *
 * Protegido por 'permiso:solicitudes-credito.ver' /
 * 'permiso:solicitudes-credito.gestionar' (ver routes/api.php).
 */
class SolicitudCreditoAdminController extends Controller
{
    public function __construct(
        private GestionSolicitudService $gestion,
    ) {}

    // ====================================================================
    //  Lectura
    // ====================================================================

    /**
     * GET /api/admin/credito/solicitudes
     * Soporta: ?campo= ?q= ?estado= ?desde= ?hasta= ?page= ?per_page=
     */
    public function index(SolicitudAdminIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $perPage = (int) ($filtros['per_page'] ?? 20);

        $solicitudes = $this->gestion->query($filtros)->paginate($perPage);

        return response()->json([
            'data' => SolicitudAdminListaResource::collection($solicitudes->items()),
            'meta' => [
                'current_page' => $solicitudes->currentPage(),
                'last_page'    => $solicitudes->lastPage(),
                'per_page'     => $solicitudes->perPage(),
                'total'        => $solicitudes->total(),

                // Para "Mostrando 1-20 de 147" sin que Angular recalcule.
                'desde' => $solicitudes->firstItem() ?? 0,
                'hasta' => $solicitudes->lastItem() ?? 0,

                // Contador global de la alerta: NO depende de los
                // filtros, porque mide trabajo pendiente total.
                'pendientes' => $this->gestion->pendientes(),
            ],
        ], 200);
    }

    /**
     * GET /api/admin/credito/solicitudes/config
     * Combos del listado y del formulario de decision.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'estados' => EstadoSolicitud::where('esc_estado', 1)
                ->orderBy('esc_orden')
                ->get(['esc_id', 'esc_codigo', 'esc_descri']),

            // Codigo + etiqueta desde la constante del modelo: un solo
            // lugar del sistema define los motivos.
            'motivos_rechazo' => collect(SolicitudCredito::MOTIVOS_RECHAZO)
                ->map(fn (string $etiqueta, string $codigo) => [
                    'codigo'    => $codigo,
                    'etiqueta'  => $etiqueta,
                ])
                ->values(),

            'campos_busqueda' => [
                ['codigo' => 'numero',  'etiqueta' => 'N.º de solicitud'],
                ['codigo' => 'ci',      'etiqueta' => 'C.I. del solicitante'],
                ['codigo' => 'cliente', 'etiqueta' => 'Nombre o apellido'],
            ],

            'vigencia_default_dias' => 7,
        ], 200);
    }

    /** GET /api/admin/credito/solicitudes/{id} */
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'data' => new SolicitudAdminResource($this->gestion->detalle($id)),
        ], 200);
    }

    /** GET /api/admin/credito/solicitudes/{id}/detalle/{detalle}/planes */
    public function planes(int $id, int $detalle): JsonResponse
    {
        $solicitud = SolicitudCredito::findOrFail($id);

        return response()->json([
            'data' => $this->gestion->planesDisponibles($solicitud, $detalle),
        ], 200);
    }

    // ====================================================================
    //  Escritura
    // ====================================================================

    /** PATCH /api/admin/credito/solicitudes/{id}/evaluar */
    public function tomar(int $id): JsonResponse
    {
        $solicitud = SolicitudCredito::with('estado')->findOrFail($id);

        $actualizada = $this->gestion->tomarEnEvaluacion($solicitud, $this->admin());

        return response()->json([
            'message' => 'Solicitud tomada en evaluación.',
            'data'    => new SolicitudAdminResource($actualizada),
        ], 200);
    }

    /** PUT /api/admin/credito/solicitudes/{id}/observaciones */
    public function observaciones(ObservacionesSolicitudRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudCredito::with('estado')->findOrFail($id);

        $actualizada = $this->gestion->guardarObservaciones(
            $solicitud,
            $request->validated('observaciones')
        );

        return response()->json([
            'message' => 'Observaciones guardadas.',
            'data'    => new SolicitudAdminResource($actualizada),
        ], 200);
    }

    /** PATCH /api/admin/credito/solicitudes/{id}/detalle/{detalle}/plan */
    public function cambiarPlan(CambiarPlanRequest $request, int $id, int $detalle): JsonResponse
    {
        $solicitud = SolicitudCredito::with('estado')->findOrFail($id);

        $actualizada = $this->gestion->cambiarPlan(
            $solicitud,
            $detalle,
            (int) $request->validated('cuotas')
        );

        return response()->json([
            'message' => 'Plan actualizado. El cronograma fue recalculado.',
            'data'    => new SolicitudAdminResource($actualizada),
        ], 200);
    }

    /** PUT /api/admin/credito/solicitudes/{id}/cronograma */
    public function cronograma(CronogramaSolicitudRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudCredito::with('estado')->findOrFail($id);

        $actualizada = $this->gestion->fijarCronograma(
            $solicitud,
            $request->validated('fecha_primera_cuota')
        );

        return response()->json([
            'message' => 'Cronograma recalculado.',
            'data'    => new SolicitudAdminResource($actualizada),
        ], 200);
    }

    /** POST /api/admin/credito/solicitudes/{id}/decision */
    public function decidir(DecisionSolicitudRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudCredito::with('estado')->findOrFail($id);

        $actualizada = $this->gestion->decidir(
            $solicitud,
            $this->admin(),
            $request->validated()
        );

        return response()->json([
            'message' => $request->validated('decision') === 'APROBAR'
                ? 'Solicitud aprobada.'
                : 'Solicitud rechazada.',
            'data' => new SolicitudAdminResource($actualizada),
        ], 200);
    }

    private function admin(): Usuario
    {
        return auth('api')->user();
    }
}