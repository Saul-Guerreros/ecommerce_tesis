<?php

namespace App\Http\Controllers\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\CheckoutEntregaRequest;
use App\Http\Requests\Pedido\CheckoutFacturacionRequest;
use App\Http\Requests\Pedido\CheckoutPagoRequest;
use App\Http\Resources\Cliente\DatoFiscalClienteResource;
use App\Http\Resources\Cliente\DireccionClienteResource;
use App\Http\Resources\Pedido\EntregaResource;
use App\Http\Resources\Pedido\FacturacionResource;
use App\Http\Resources\Pedido\MetodoPagoResource;
use App\Http\Resources\Pedido\PagoResource;
use App\Http\Resources\Pedido\SucursalRetiroResource;
use App\Models\Usuario;
use App\Services\Pedido\CheckoutEntregaService;
use App\Services\Pedido\CheckoutFacturacionService;
use App\Services\Pedido\CheckoutPagoService;
use App\Services\Pedido\PedidoService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutEntregaService $servicio,
        private CheckoutFacturacionService $servicioFacturacion,
        private CheckoutPagoService $servicioPago,
        private PedidoService $servicioPedido,
    ) {
        $this->middleware('auth:api');
    }

    public function configEntrega(): JsonResponse
    {
        $usuario  = $this->clienteAutenticado();
        $datos    = $this->servicio->datosIniciales($usuario);
        $guardado = $this->servicio->borrador($usuario);

        return response()->json([
            'sucursales'  => SucursalRetiroResource::collection($datos['sucursales']),
            'direcciones' => DireccionClienteResource::collection($datos['direcciones']),
            'titular'     => $datos['titular'],
            'envio'       => $datos['envio'],
            'entrega'     => $guardado ? new EntregaResource($guardado) : null,
        ], 200);
    }

    public function guardarEntrega(CheckoutEntregaRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $entrega = $this->servicio->guardar($usuario, $request->validated());

        return response()->json([
            'message' => 'Datos de entrega guardados.',
            'data'    => new EntregaResource($entrega),
        ], 200);
    }

    public function configFacturacion(): JsonResponse
    {
        $usuario  = $this->clienteAutenticado();
        $datos    = $this->servicioFacturacion->datosIniciales($usuario);
        $guardado = $this->servicioFacturacion->borrador($usuario);

        return response()->json([
            'titular'        => $datos['titular'],
            'datos_fiscales' => DatoFiscalClienteResource::collection($datos['datos_fiscales']),
            'entrega'        => $datos['entrega'] ? new EntregaResource($datos['entrega']) : null,
            'resumen'        => $datos['resumen'],
            'facturacion'    => $guardado ? new FacturacionResource($guardado) : null,
        ], 200);
    }

    public function guardarFacturacion(CheckoutFacturacionRequest $request): JsonResponse
    {
        $usuario     = $this->clienteAutenticado();
        $facturacion = $this->servicioFacturacion->guardar($usuario, $request->validated());

        return response()->json([
            'message' => 'Datos de facturación guardados.',
            'data'    => new FacturacionResource($facturacion),
        ], 200);
    }

    /**
     * total_pedido se agrega para el pago dividido: es el valor contra el
     * que el cliente reparte los montos. Se calcula en el backend, nunca en
     * Angular, porque es el mismo numero que despues valida el guardado.
     */
    public function configPago(): JsonResponse
    {
        $usuario  = $this->clienteAutenticado();
        $datos    = $this->servicioPago->datosIniciales();
        $guardado = $this->servicioPago->borrador($usuario);

        return response()->json([
            'metodos'             => MetodoPagoResource::collection($datos['metodos']),
            'datos_transferencia' => $datos['datos_transferencia'],
            'datos_billetera'     => $datos['datos_billetera'],
            'mensaje_efectivo'    => $datos['mensaje_efectivo'],
            'total_pedido'        => $this->servicioPago->totalExigible($usuario),
            'pago'                => $guardado ? new PagoResource($guardado) : null,
        ], 200);
    }

    public function guardarPago(CheckoutPagoRequest $request): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pago    = $this->servicioPago->guardar($usuario, $request->validated());

        return response()->json([
            'message' => $request->esDividido()
                ? 'Pagos registrados.'
                : 'Método de pago guardado.',
            'data'    => new PagoResource($pago),
        ], 200);
    }

    public function configConfirmacion(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $datos   = $this->servicioPedido->datosConfirmacion($usuario);

        return response()->json([
            'items'       => $datos['items'],
            'entrega'     => new EntregaResource($datos['entrega']),
            'facturacion' => new FacturacionResource($datos['facturacion']),
            'pago'        => new PagoResource($datos['pago']),
            'resumen'     => $datos['resumen'],
        ], 200);
    }

    public function confirmar(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicioPedido->confirmar($usuario);

        return response()->json([
            'message' => 'Pedido realizado con éxito.',
            'data' => [
                'ped_id'     => $pedido->ped_id,
                'ped_numero' => $pedido->ped_numero,
                'ped_total'  => (float) $pedido->ped_total,
                'ped_creado' => $pedido->ped_creado,
            ],
        ], 201);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}