<?php

namespace App\Http\Controllers\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\CheckoutEntregaRequest;
use App\Http\Requests\Pedido\CheckoutFacturacionRequest;
use App\Http\Resources\Carrito\CarritoResource; // también se usa en configEntrega()
use App\Http\Resources\Cliente\DatoFiscalClienteResource;
use App\Http\Resources\Cliente\DireccionClienteResource;
use App\Http\Resources\Pedido\EntregaResource;
use App\Http\Resources\Pedido\FacturacionResource;
use App\Http\Resources\Pedido\SucursalRetiroResource;
use App\Models\Usuario;
use App\Services\Pedido\CheckoutEntregaService;
use App\Services\Pedido\CheckoutFacturacionService;
use App\Services\Pedido\CheckoutResumenService;
use App\Services\Pedido\PedidoService;
use Illuminate\Http\JsonResponse;

/**
 * Checkout de la tienda web, lado cliente.
 *
 *   Paso 1  Entrega        -> configEntrega / guardarEntrega
 *   Paso 2  Facturación    -> configFacturacion / guardarFacturacion
 *                             + confirmar()  <-- crea el pedido y reserva stock
 *   Paso 3  Pago           -> PagoPedidoController (Etapa 3), ya contra un
 *                             pedido existente
 *
 * El Paso 3 anterior ("Confirmación") desaparece: su resumen pasa al Paso 2,
 * porque confirmar el pedido ahora compromete stock y el cliente tiene que
 * ver qué está por confirmar antes de hacerlo.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutEntregaService $servicio,
        private CheckoutFacturacionService $servicioFacturacion,
        private CheckoutResumenService $servicioResumen,
        private PedidoService $servicioPedido,
    ) {
        $this->middleware('auth:api');
    }

    // ---------- Paso 1: Entrega ----------

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

            // Alimenta la tarjeta "Productos en Carrito" del Paso 1.
            'items'       => CarritoResource::collection($this->servicioResumen->itemsOpcionales($usuario)),

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

    // ---------- Paso 2: Facturación ----------

    /**
     * 'items' se agrega con el rediseño: el Paso 2 es ahora el último punto
     * antes de comprometer stock, así que el cliente tiene que poder revisar
     * los productos acá y no en una pantalla posterior que ya no existe.
     */
    public function configFacturacion(): JsonResponse
    {
        $usuario  = $this->clienteAutenticado();
        $datos    = $this->servicioFacturacion->datosIniciales($usuario);
        $guardado = $this->servicioFacturacion->borrador($usuario);

        return response()->json([
            'titular'        => $datos['titular'],
            'datos_fiscales' => DatoFiscalClienteResource::collection($datos['datos_fiscales']),
            'entrega'        => $datos['entrega'] ? new EntregaResource($datos['entrega']) : null,
            'items'          => CarritoResource::collection($this->servicioResumen->itemsOpcionales($usuario)),
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
     * Botón [CONFIRMAR PEDIDO] del Paso 2.
     *
     * Crea el pedido y toma la reserva de stock. Si no hay stock, falla acá
     * (422) y el cliente no pagó nada todavía: ése es el objetivo de todo
     * el rediseño.
     *
     * Devuelve ped_vence para que la pantalla de pago pueda mostrar el plazo
     * ("vence en 24 h") sin tener que calcularlo por su cuenta.
     */
    public function confirmar(): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicioPedido->confirmar($usuario);

        return response()->json([
            'message' => 'Pedido creado. Ya reservamos tu stock.',
            'data'    => [
                'ped_id'     => $pedido->ped_id,
                'ped_numero' => $pedido->ped_numero,
                'ped_total'  => (float) $pedido->ped_total,
                'ped_creado' => $pedido->ped_creado,
                'ped_vence'  => $pedido->ped_vence,
                'estado_pago' => $pedido->estadoPago?->epa_codigo,
            ],
        ], 201);
    }

    // ---------- Helpers ----------

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}