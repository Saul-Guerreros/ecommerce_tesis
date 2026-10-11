<?php

namespace App\Http\Controllers\Admin\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\CambiarEstadoPedidoRequest;
use App\Http\Requests\Pedido\ConfirmarPagoRequest;
use App\Http\Requests\Pedido\RechazarPagoRequest;
use App\Http\Resources\Pedido\PedidoDetalleResource;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\Pedido;
use App\Services\Pedido\PagoVerificacionService;
use App\Services\Pedido\PedidoEstadoService;
use Illuminate\Http\JsonResponse;

use App\Models\Pedido\PedidoPago;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;


/**
 * Gestión del Pedido: detalle, verificación de pagos y cambio de estado.
 *
 * Se separa de PedidoController (la bandeja, solo lectura) porque este
 * controlador sí muta: mezclarlos obligaría a proteger el listado con el
 * permiso más restrictivo de los dos.
 *
 * El controlador no persiste nada por su cuenta: delega en
 * PagoVerificacionService y PedidoEstadoService, que son los que abren la
 * transacción (plantilla de módulos transaccionales del estándar).
 */
class PedidoGestionController extends Controller
{
    /**
     * Eager loading explícito, acorde a lo que PedidoDetalleResource lee.
     */
    private const RELACIONES = [
        'cliente.persona.contactos',
        'cliente.persona.usuario',
        'cliente.tipoCliente',
        'tipoEntrega',
        'estadoPedido',
        'estadoPago',
        'metodoPago',
        'detalles.producto.tipoIva',
        'entrega.sucursal.ciudad',
        'facturacion',
        'pagos.metodoPago',
        'pagos.estadoPago',
        'pagos.comprobante',
        'pagos.motivoRechazo',
        'pagos.usuarioVerificador.persona',
    ];

    public function __construct(
        private PagoVerificacionService $verificacion,
        private PedidoEstadoService $estados,
    ) {}

    /**
     * GET /api/admin/pedidos/{pedido}
     */
    public function show(Pedido $pedido): JsonResponse
    {
        return response()->json([
            'data' => $this->serializar($pedido),
        ]);
    }

    /**
     * GET /api/admin/pedidos/gestion/config
     * Catálogo de motivos para el modal de rechazo.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'motivos_rechazo' => MotivoRechazoPago::activos()
                ->orderBy('mrp_id')
                ->get(['mrp_id', 'mrp_codigo', 'mrp_descri', 'mrp_requdet']),
        ]);
    }

    /**
     * POST /api/admin/pedidos/{pedido}/pagos/{pago}/confirmar
     *
     * Si con este pago el pedido queda totalmente cobrado, el service
     * descuenta stock y lo pasa a En preparación en la misma transacción.
     * Un StockInsuficienteException revierte todo y responde 409.
     */
    public function confirmarPago(ConfirmarPagoRequest $request, Pedido $pedido, int $pago): JsonResponse
    {
        $actualizado = $this->verificacion->confirmar(
            $pedido,
            $pago,
            $request->validated(),
            auth('api')->user(),
        );

        return response()->json([
            'message' => 'Pago confirmado correctamente.',
            'data'    => $this->serializar($actualizado),
        ]);
    }

    /**
     * POST /api/admin/pedidos/{pedido}/pagos/{pago}/rechazar
     */
    public function rechazarPago(RechazarPagoRequest $request, Pedido $pedido, int $pago): JsonResponse
    {
        $actualizado = $this->verificacion->rechazar(
            $pedido,
            $pago,
            $request->validated(),
            auth('api')->user(),
        );

        return response()->json([
            'message' => 'Pago rechazado. El cliente podrá subir un nuevo comprobante.',
            'data'    => $this->serializar($actualizado),
        ]);
    }

    /**
     * PATCH /api/admin/pedidos/{pedido}/estado
     */
    public function cambiarEstado(CambiarEstadoPedidoRequest $request, Pedido $pedido): JsonResponse
    {
        $pedido->load(['estadoPedido', 'estadoPago']);

        $actualizado = $this->estados->cambiar(
            $pedido,
            $request->validated()['estado'],
            auth('api')->user(),
            $request->validated()['comentario'] ?? null,
        );

        return response()->json([
            'message' => 'Estado del pedido actualizado.',
            'data'    => $this->serializar($actualizado),
        ]);
    }

    // ---------- Auxiliares de serialización ----------

    /**
     * Recarga las relaciones y adjunta los datos que no salen del modelo
     * Pedido: las transiciones habilitadas y las métricas del cliente.
     */
    private function serializar(Pedido $pedido): array
    {
        $pedido = $pedido->fresh(self::RELACIONES);

        $recurso = new PedidoDetalleResource($pedido);
        $recurso->additional([
            'transiciones'       => $this->estados->transicionesPermitidas($pedido),
            'pedidos_anteriores' => $this->contarPedidosAnteriores($pedido),
            'cliente_desde'      => $this->clienteDesde($pedido),
        ]);

        return $recurso->resolve();
    }

    private function contarPedidosAnteriores(Pedido $pedido): int
    {
        if (!$pedido->ped_cliid) {
            return 0;
        }

        return Pedido::where('ped_cliid', $pedido->ped_cliid)
            ->where('ped_id', '!=', $pedido->ped_id)
            ->count();
    }

    /**
     * La tabla cliente no guarda fecha de alta, así que «Cliente desde»
     * se deriva del primer pedido registrado. Es el dato real disponible;
     * inventar una fecha sería peor que mostrar null.
     */
    private function clienteDesde(Pedido $pedido): ?string
    {
        if (!$pedido->ped_cliid) {
            return null;
        }

        return Pedido::where('ped_cliid', $pedido->ped_cliid)
            ->min('ped_creado')
            ? \Illuminate\Support\Carbon::parse(
                Pedido::where('ped_cliid', $pedido->ped_cliid)->min('ped_creado')
            )->format('d/m/Y')
            : null;
    }

        /**
     * GET /api/admin/pedidos/{pedido}/pagos/{pago}/comprobante
     *
     * Sirve el comprobante por la API en lugar de exponer /storage.
     * Dos razones: el comprobante bancario de un cliente no puede quedar
     * accesible por URL pública sin autenticación, y el servidor embebido
     * de PHP entrega los archivos de public/ sin pasar por el middleware
     * de CORS, lo que impide leerlos desde Angular.
     */
    public function comprobante(Pedido $pedido, int $pago): StreamedResponse
    {
        $registro = PedidoPago::where('ppa_id', $pago)
            ->where('ppa_pedid', $pedido->ped_id)
            ->firstOrFail();

        $comprobante = $registro->comprobante;

        abort_if(!$comprobante, 404, 'Este pago no tiene comprobante cargado.');

        $disco = Storage::disk('public');

        abort_if(
            !$disco->exists($comprobante->pco_archiv),
            404,
            'El archivo del comprobante ya no está disponible en el servidor.'
        );

        return $disco->response($comprobante->pco_archiv, basename($comprobante->pco_archiv));
    }
}