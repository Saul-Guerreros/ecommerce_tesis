<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\ClienteDocumentoRequest;
use App\Http\Requests\Cliente\ClienteRapidoRequest;
use App\Http\Requests\Servicio\RegistrarMostradorRequest;
use App\Models\Cliente;
use App\Models\Servicio\TipoDispositivo;
use App\Models\TipoDocumento;
use App\Services\Cliente\ClienteRapidoService;
use App\Services\Servicio\NotificadorServicio;
use App\Services\Servicio\SolicitudServicioService;
use App\Services\Servicio\TecnicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ingreso presencial ("mostrador"): segundo canal de entrada al flujo de servicio técnico.
 * Lo usa el wizard /servicios/solicitudes/mostrador del panel (rol Vendedor o Administrador).
 * El controlador no persiste: delega en ClienteRapidoService y SolicitudServicioService.
 */
class MostradorServicioController extends Controller
{
    public function __construct(
        private SolicitudServicioService $solicitudes,
        private ClienteRapidoService $clientes,
        private TecnicoService $tecnicos,
        private NotificadorServicio $notificador,
    ) {}

    // GET /api/admin/servicios/solicitudes/mostrador/config
    public function config(): JsonResponse
    {
        $usuario = auth('api')->user();

        return response()->json([
            'sucursales'         => $this->solicitudes->sucursalesMostrador($usuario),
            'sucursal_principal' => $this->solicitudes->sucursalPrincipal($usuario),
            'tipos_dispositivo'  => TipoDispositivo::activos()->orderBy('tdi_descri')->get(['tdi_id', 'tdi_codigo', 'tdi_descri']),
            'tipos_documento'    => TipoDocumento::where('tpd_estado', 1)
                ->whereIn('tpd_codigo', ClienteRapidoService::DOCUMENTOS_MOSTRADOR)
                ->orderBy('tpd_id')->get(['tpd_id', 'tpd_codigo', 'tpd_descri']),
            // Cada técnico trae sus 'sucursales': el wizard filtra el combo según la sucursal elegida.
            'tecnicos'           => $this->tecnicos->activos(),
            'max_fotos'          => (int) config('servicio.max_fotos_ingreso'),
            'foto_max_kb'        => (int) config('servicio.foto_max_kb'),
        ], 200);
    }

    // GET /api/admin/servicios/solicitudes/mostrador/clientes?q=   (documento, correo o celular)
    public function buscarClientes(Request $request): JsonResponse
    {
        $request->validate(
            ['q' => ['required', 'string', 'min:4', 'max:100']],
            ['q.required' => 'Ingrese un documento, correo o celular.', 'q.min' => 'Ingrese al menos 4 caracteres.'],
        );

        return response()->json(['data' => $this->clientes->buscar($request->input('q'))], 200);
    }

    // POST /api/admin/servicios/solicitudes/mostrador/clientes
    public function crearCliente(ClienteRapidoRequest $request): JsonResponse
    {
        $cliente = $this->clientes->altaRapida($request->validated());

        return response()->json([
            'message' => 'Cliente registrado correctamente',
            'data'    => $this->clientes->resumen($cliente->fresh()),
        ], 201);
    }

    // PUT /api/admin/servicios/solicitudes/mostrador/clientes/{cliId}/documento
    public function completarDocumento(ClienteDocumentoRequest $request, int $cliId): JsonResponse
    {
        $cliente = Cliente::with('persona')->findOrFail($cliId);
        $cliente = $this->clientes->completarDocumento(
            $cliente, (int) $request->validated('per_tpdid'), $request->validated('per_cedula')
        );

        return response()->json([
            'message' => 'Documento registrado',
            'data'    => $this->clientes->resumen($cliente),
        ], 200);
    }

    // GET /api/admin/servicios/solicitudes/mostrador/clientes/{cliId}/garantias
    public function garantias(int $cliId): JsonResponse
    {
        $cliente = Cliente::findOrFail($cliId);

        return response()->json([
            'data' => $this->solicitudes->lineasEnGarantia($cliente)->map(fn ($l) => [
                ...$l,
                'vence' => $l['vence']->format('Y-m-d'),
            ])->values(),
        ], 200);
    }

    // GET /api/admin/servicios/solicitudes/mostrador/clientes/{cliId}/reclamables
    public function reclamables(int $cliId): JsonResponse
    {
        return response()->json(['data' => $this->solicitudes->reclamablesDe(Cliente::findOrFail($cliId))], 200);
    }

    // GET /api/admin/servicios/solicitudes/mostrador/reclamo/{numero}   (wizard con ?reclamo=SS-...)
    public function reclamo(string $numero): JsonResponse
    {
        $original = $this->solicitudes->reclamoPorNumero($numero);
        $original->load(['tipoDispositivo', 'entrega', 'ordenServicio']);

        return response()->json([
            'data' => [
                'cliente'  => $this->clientes->resumen($original->cliente),
                'original' => $this->solicitudes->datosReclamable($original),
            ],
        ], 200);
    }

    // POST /api/admin/servicios/solicitudes/mostrador   (multipart/form-data)
    public function store(RegistrarMostradorRequest $request): JsonResponse
    {
        $s = $this->solicitudes->registrarMostrador(
            auth('api')->user(), $request->validated(), $request->file('fotos', [])
        );

        $s->load(['cliente.persona.contactos', 'cliente.persona.usuario', 'estadoServicio']);

        return response()->json([
            'message' => "Solicitud {$s->sse_numero} registrada y recibida correctamente",
            'data'    => [
                'sse_id'       => $s->sse_id,
                'sse_numero'   => $s->sse_numero,
                'cliente'      => $s->cliente?->persona?->nombre_completo,
                'tiene_correo' => $s->cliente?->persona?->contactos?->where('con_tipo', 'EMAIL')->isNotEmpty()
                    || $s->cliente?->persona?->usuario !== null,
                'whatsapp_url' => $this->notificador->enlaceWhatsapp($s),
            ],
        ], 201);
    }
}
