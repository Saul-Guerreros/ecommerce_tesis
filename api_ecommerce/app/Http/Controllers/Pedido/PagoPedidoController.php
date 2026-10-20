<?php

namespace App\Http\Controllers\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\PagoPedidoRequest;
use App\Http\Requests\Pedido\ReemplazarComprobanteRequest;
use App\Http\Resources\Pedido\MetodoPagoResource;
use App\Http\Resources\Pedido\ProductoResumenResource;
use App\Http\Resources\Pedido\ResultadoPagoResource;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use App\Models\Usuario;
use App\Services\Pedido\PagoPedidoService;
use Illuminate\Http\JsonResponse;

/**
 * Paso 3 del checkout: pago de un pedido que ya existe.
 *
 * Se separa de CheckoutController a propósito. Los pasos 1 y 2 operan
 * sobre un borrador del usuario (pedido_temporal) y no tienen recurso
 * propio; acá el recurso es un pedido concreto, identificado por su número
 * en la URL. Eso es lo que hace que la pantalla sea reanudable: el cliente
 * puede cerrar el navegador y volver al link después, sin depender de
 * ningún borrador ni de que el carrito siga lleno.
 */
class PagoPedidoController extends Controller
{
    public function __construct(
        private PagoPedidoService $servicio,
    ) {
        $this->middleware('auth:api');
    }

    /**
     * Datos de la pantalla de pago: el pedido a pagar y las formas de pago
     * de la tienda.
     *
     * Devuelve 422 si el pedido ya fue pagado o venció; el frontend usa esa
     * respuesta para mandar al cliente a la pantalla de resultado o a
     * "Mis pedidos" en vez de mostrar un formulario inútil.
     */
    public function config(string $numero): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicio->pedidoPagable($usuario, $numero);
        $datos   = $this->servicio->datosIniciales();

        return response()->json([
            'pedido' => [
                'ped_numero' => $pedido->ped_numero,
                'ped_total'  => (float) $pedido->ped_total,
                'ped_creado' => $pedido->ped_creado,
                'ped_vence'  => $pedido->ped_vence,
            ],
            'metodos'             => MetodoPagoResource::collection($datos['metodos']),
            'datos_transferencia' => $datos['datos_transferencia'],
            'datos_billetera'     => $datos['datos_billetera'],
            'mensaje_efectivo'    => $datos['mensaje_efectivo'],

            // Tarjetas de resumen del Paso 3 (Resumen de Compra, Productos
            // en Carrito, Datos de Entrega, Datos de Facturación). El
            // pedido ya está confirmado, así que estos datos no cambian:
            // vienen de lo que PedidoService persistió, no de un borrador.
            'resumen' => [
                'subtotal'    => (float) $pedido->ped_subtot,
                'impuestos'   => (float) $pedido->ped_impues,
                'costo_envio' => (float) $pedido->ped_cosenv,
                'total'       => (float) $pedido->ped_total,
            ],
            'productos'   => ProductoResumenResource::collection($pedido->detalles),
            'entrega'     => $this->entregaResumen($pedido),
            'facturacion' => $this->facturacionResumen($pedido),
        ], 200);
    }

    /**
     * Misma forma de salida que PedidoClienteDetalleResource::armarEntrega()
     * (Mis Pedidos): las dos pantallas muestran esta tarjeta "tal cual", así
     * que el frontend usa un único tipo para las dos sin tener que adaptar
     * nada entre una y otra.
     */
    private function entregaResumen(Pedido $pedido): ?array
    {
        $entrega = $pedido->entrega;

        if (!$entrega) {
            return null;
        }

        $esRetiro = $pedido->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO;
        $sucursal = $entrega->sucursal;

        return [
            'modalidad'          => $esRetiro ? 'retiro' : 'envio',
            'receptor_nombre'    => trim(($entrega->pen_nombre ?? '') . ' ' . ($entrega->pen_apelli ?? '')),
            'receptor_telefono'  => $entrega->pen_telef,
            'receptor_documento' => $entrega->pen_nrodoc,

            'suc_nombre'    => $sucursal?->suc_nombre,
            'suc_direccion' => $sucursal?->suc_direccion,
            'suc_horario'   => $sucursal?->suc_horario,

            'calle'        => $entrega->pen_calle,
            'nrocasa'      => $entrega->pen_nrocasa,
            'barrio'       => $entrega->pen_barrio,
            'ciudad'       => $entrega->pen_ciudad,
            'departamento' => $entrega->pen_departa,
            'referencia'   => $entrega->pen_referencia,
        ];
    }

    private function facturacionResumen(Pedido $pedido): ?array
    {
        $facturacion = $pedido->facturacion;

        if (!$facturacion) {
            return null;
        }

        return [
            'tipo_documento' => $facturacion->pfa_tipodoc,
            'nro_documento'  => $facturacion->pfa_nrodoc,
            'razon_social'   => $facturacion->pfa_raznom,
            'recibe_por'     => $facturacion->pfa_recibe,
        ];
    }

    /**
     * Registra el pago declarado por el cliente.
     *
     * 201: se creó un recurso nuevo (las filas de pedido_pago). Devuelve ya
     * el resultado armado para que el frontend no tenga que hacer un GET
     * extra apenas navega a la pantalla final.
     */
    public function registrar(PagoPedidoRequest $request, string $numero): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicio->registrar($usuario, $numero, $request->validated());

        return response()->json([
            'message' => $request->esDividido()
                ? 'Pagos registrados.'
                : 'Pago registrado.',
            'data'    => new ResultadoPagoResource($pedido),
        ], 201);
    }

    /**
     * POST /api/tienda/pedidos/{numero}/pagos/{pago}/comprobante
     *
     * Reemplaza el comprobante de un pago que el admin rechazó. Es la
     * salida del cliente ante un rechazo: sin este endpoint, un
     * comprobante rechazado deja el pedido sin camino posible y la
     * reserva termina expirando sola.
     *
     * 200 y no 201: no se crea un recurso nuevo, se corrige un pago que
     * ya existe.
     */
    public function reemplazarComprobante(
        ReemplazarComprobanteRequest $request,
        string $numero,
        int $pago,
    ): JsonResponse {
        $usuario = $this->clienteAutenticado();

        $pedido = $this->servicio->reemplazarComprobante(
            $usuario,
            $numero,
            $pago,
            $request->file('comprobante'),
        );

        return response()->json([
            'message' => 'Comprobante enviado. Lo vamos a verificar a la brevedad.',
            'data'    => new ResultadoPagoResource($pedido),
        ], 200);
    }

    /**
     * Pantalla final (los 3 casos). Vive en su propia ruta y se resuelve
     * por número de pedido, así que sigue siendo válida de forma
     * permanente: el cliente puede recargarla o volver con el botón del
     * navegador sin encontrarse una página muerta.
     */
    public function resultado(string $numero): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicio->pedidoConPago($usuario, $numero);

        return response()->json([
            'data' => new ResultadoPagoResource($pedido),
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