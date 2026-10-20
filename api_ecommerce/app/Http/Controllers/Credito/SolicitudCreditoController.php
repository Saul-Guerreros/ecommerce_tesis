<?php

namespace App\Http\Controllers\Credito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credito\GenerarSolicitudRequest;
use App\Http\Requests\Credito\SolicitudLaboralRequest;
use App\Http\Requests\Credito\SolicitudPersonalRequest;
use App\Http\Requests\Credito\SolicitudReferenciasRequest;
use App\Http\Resources\Carrito\CarritoResource;
use App\Http\Resources\Credito\EstadoSolicitudResource;
use App\Http\Resources\Credito\ProfesionResource;
use App\Http\Resources\Credito\SolicitudCreditoResource;
use App\Http\Resources\Credito\SolicitudLaboralResource;
use App\Http\Resources\Credito\SolicitudPersonalResource;
use App\Http\Resources\Credito\SolicitudReferenciaResource;
use App\Http\Resources\Credito\TipoProfesionResource;
use App\Models\Usuario;
use App\Services\Credito\SolicitudBorradorService;
use App\Services\Credito\SolicitudService;
use Illuminate\Http\JsonResponse;

/**
 * Wizard de Solicitud de Crédito (lado cliente del ecommerce).
 *
 * Mismo patrón de CheckoutController: GET de configuración + POST de
 * guardado por paso, y dos endpoints de cierre (confirmación / generar).
 * La diferencia es que acá son 3 pasos de datos en vez de 3 de checkout,
 * así que el controlador queda más largo pero cada método sigue el
 * mismo esquema.
 */
class SolicitudCreditoController extends Controller
{
    public function __construct(
        private SolicitudBorradorService $borrador,
        private SolicitudService $servicio,
    ) {
        $this->middleware('auth:api');
    }

    // ---------- Configuración inicial (catálogos + precarga de perfil) ----------

    public function config(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $datos   = $this->borrador->datosIniciales($usuario);
        $temporal = $this->borrador->borrador($usuario);

        return response()->json([
            'tipos_profesion' => TipoProfesionResource::collection($datos['tipos_profesion']),
            'profesiones'     => ProfesionResource::collection($datos['profesiones']),
            'estados_civiles' => $datos['estados_civiles'],
            'tipos_vivienda'  => $datos['tipos_vivienda'],
            'relaciones'      => $datos['relaciones'],
            'perfil'          => $datos['perfil'],

            // Totales del credito para el panel lateral del wizard,
            // visible en los 4 pasos (no solo en Confirmacion).
            'resumen' => $this->servicio->resumenDelCarrito($usuario),

            // El frontend usa esto para saltar directo al paso correcto
            // si el cliente vuelve a entrar con un borrador a medias.
            'paso_actual' => $temporal?->primerPasoPendiente() ?? 1,

            'personal'    => $temporal?->tienePersonal()
                ? new SolicitudPersonalResource($temporal->stm_person) : null,
            'laboral'     => $temporal?->tieneLaboral()
                ? new SolicitudLaboralResource($temporal->stm_labora) : null,
            'referencias' => $temporal?->tieneReferencias()
                ? SolicitudReferenciaResource::collection($temporal->stm_refere) : null,
        ], 200);
    }

    // ---------- Paso 1: Datos personales ----------

    public function guardarPersonal(SolicitudPersonalRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $bloque  = $this->borrador->guardarPersonal($usuario, $request->validated());

        return response()->json([
            'message' => 'Datos personales guardados.',
            'data'    => new SolicitudPersonalResource($bloque),
        ], 200);
    }

    // ---------- Paso 2: Datos laborales ----------

    public function guardarLaboral(SolicitudLaboralRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $bloque  = $this->borrador->guardarLaboral($usuario, $request->validated());

        return response()->json([
            'message' => 'Datos laborales guardados.',
            'data'    => new SolicitudLaboralResource($bloque),
        ], 200);
    }

    // ---------- Paso 3: Referencias personales ----------

    public function guardarReferencias(SolicitudReferenciasRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $bloque  = $this->borrador->guardarReferencias($usuario, $request->validated('referencias'));

        return response()->json([
            'message' => 'Referencias guardadas.',
            'data'    => SolicitudReferenciaResource::collection($bloque),
        ], 200);
    }

    // ---------- Paso 4: Confirmar solicitud ----------

    public function configConfirmacion(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $datos   = $this->servicio->datosConfirmacion($usuario);

        return response()->json([
            'personal'    => new SolicitudPersonalResource($datos['personal']),
            'laboral'     => new SolicitudLaboralResource($datos['laboral']),
            'referencias' => SolicitudReferenciaResource::collection($datos['referencias']),
            'resumen'     => $datos['resumen'],
            'items'       => CarritoResource::collection($datos['items']),
        ], 200);
    }

    public function generar(GenerarSolicitudRequest $request): JsonResponse
    {
        $usuario   = $this->clienteAutenticado();
        $solicitud = $this->servicio->generar($usuario);

        $solicitud->load(['estado', 'ciudad.departamento', 'profesion', 'tipoProfesion', 'detalles', 'referencias']);

        return response()->json([
            'message' => 'Solicitud enviada correctamente.',
            'data'    => new SolicitudCreditoResource($solicitud),
        ], 201);
    }

    // ---------- "Mis solicitudes" ----------

    public function index(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();

        return response()->json([
            'data' => SolicitudCreditoResource::collection($this->servicio->listarDelCliente($usuario)),
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->clienteAutenticado();

        return response()->json([
            'data' => new SolicitudCreditoResource($this->servicio->detalleDelCliente($usuario, $id)),
        ], 200);
    }

    // ---------- Catálogo de estados (uso puntual del frontend, ej. leyenda) ----------

    public function estados(): JsonResponse
    {
        return response()->json([
            'data' => EstadoSolicitudResource::collection(
                \App\Models\Credito\EstadoSolicitud::where('esc_estado', 1)->orderBy('esc_orden')->get()
            ),
        ], 200);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}