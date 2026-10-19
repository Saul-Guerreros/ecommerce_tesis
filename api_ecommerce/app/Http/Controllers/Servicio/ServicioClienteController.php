<?php

namespace App\Http\Controllers\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\RechazarPresupuestoRequest;
use App\Http\Requests\Servicio\SolicitudServicioStoreRequest;
use App\Http\Resources\Cliente\DireccionClienteResource;
use App\Http\Resources\Pedido\SucursalRetiroResource;
use App\Http\Resources\Servicio\ServicioClienteResource;
use App\Models\Usuario;
use App\Services\Servicio\ConstanciaServicioService;
use App\Services\Servicio\PresupuestoService;
use App\Services\Servicio\SolicitudServicioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Servicio técnico - lado CLIENTE (tienda): C2 wizard, C3 listado, C4 detalle.
 */
class ServicioClienteController extends Controller
{
    public function __construct(
        private SolicitudServicioService $servicio,
        private PresupuestoService $presupuestos,
        private ConstanciaServicioService $constancias,
    ) {}

    // GET /api/tienda/servicios/config
    public function config(): JsonResponse
    {
        $datos = $this->servicio->configuracion($this->clienteAutenticado());

        return response()->json([
            'tipos_dispositivo' => $datos['tipos_dispositivo'],
            'sucursales'        => SucursalRetiroResource::collection($datos['sucursales']),
            'direcciones'       => DireccionClienteResource::collection($datos['direcciones']),
            'pedidos_garantia'  => $datos['pedidos_garantia']->map(fn ($p) => [
                'ped_id'     => $p->ped_id,
                'ped_numero' => $p->ped_numero,
                'fecha'      => $p->ped_creado?->format('Y-m-d'),
                'productos'  => $p->detalles->pluck('dpd_descri')->all(),
            ]),
            'garantia_dias' => $datos['garantia_dias'],
            'max_fotos'     => $datos['max_fotos'],
        ], 200);
    }

    // GET /api/tienda/servicios/costo-retiro/{dirId}
    public function costoRetiro(int $dirId): JsonResponse
    {
        $retiro = $this->servicio->costoRetiro($this->clienteAutenticado(), $dirId);

        return response()->json(['costo' => $retiro['costo']], 200);
    }

    // GET /api/tienda/servicios
    public function index(): JsonResponse
    {
        $lista = $this->servicio->listarDelCliente($this->clienteAutenticado());

        return response()->json(['data' => ServicioClienteResource::collection($lista)], 200);
    }

    // GET /api/tienda/servicios/{numero}
    public function show(string $numero): JsonResponse
    {
        $solicitud = $this->servicio->detalleDelCliente($this->clienteAutenticado(), $numero);

        return response()->json(['data' => new ServicioClienteResource($solicitud)], 200);
    }

    // POST /api/tienda/servicios   (multipart/form-data)
    public function store(SolicitudServicioStoreRequest $request): JsonResponse
    {
        $solicitud = $this->servicio->crear(
            $this->clienteAutenticado(),
            $request->validated(),
            $request->file('fotos', []),
        );

        return response()->json([
            'message' => "Recibimos tu solicitud {$solicitud->sse_numero}.",
            'data'    => ['sse_numero' => $solicitud->sse_numero],
        ], 201);
    }

    // POST /api/tienda/servicios/{numero}/presupuesto/aprobar
    public function aprobarPresupuesto(string $numero): JsonResponse
    {
        $usuario   = $this->clienteAutenticado();
        $solicitud = $this->servicio->detalleDelCliente($usuario, $numero);

        $this->presupuestos->aprobarPorCliente($solicitud, $usuario);

        return response()->json([
            'message' => '¡Gracias! Aprobaste el presupuesto: comenzamos con la reparación.',
            'data'    => new ServicioClienteResource($this->servicio->detalleDelCliente($usuario, $numero)),
        ], 200);
    }

    // POST /api/tienda/servicios/{numero}/presupuesto/rechazar
    public function rechazarPresupuesto(RechazarPresupuestoRequest $request, string $numero): JsonResponse
    {
        $usuario   = $this->clienteAutenticado();
        $solicitud = $this->servicio->detalleDelCliente($usuario, $numero);

        $this->presupuestos->rechazarPorCliente($solicitud, $request->validated('motivo'), $usuario);

        return response()->json([
            'message' => 'Registramos tu respuesta. Te avisamos cuando puedas retirar el equipo.',
            'data'    => new ServicioClienteResource($this->servicio->detalleDelCliente($usuario, $numero)),
        ], 200);
    }

    // GET /api/tienda/servicios/{numero}/constancia/{tipo}  — solo de SUS servicios
    public function constancia(string $numero, string $tipo): Response
    {
        $solicitud = $this->servicio->detalleDelCliente($this->clienteAutenticado(), $numero);

        return $this->constancias->generar($solicitud, $tipo);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona.cliente');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}
