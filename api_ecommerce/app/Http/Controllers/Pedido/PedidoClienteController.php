<?php

namespace App\Http\Controllers\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pedido\PedidoClienteDetalleResource;
use App\Http\Resources\Pedido\PedidoClienteListaResource;
use App\Http\Resources\Pedido\PedidoPendienteResource;
use App\Models\Cliente;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use App\Services\Pedido\PedidoClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Mis Pedidos": lado cliente de la tienda.
 *
 * Es el lugar al que se manda al cliente que cerró el navegador en medio
 * del checkout y perdió de vista el link de pago: acá encuentra todos sus
 * pedidos, con un botón directo a retomar el pago de cualquiera que
 * siga pendiente.
 */
class PedidoClienteController extends Controller
{
    public function __construct(
        private PedidoClienteService $servicio,
    ) {
        $this->middleware('auth:api');
    }

    /**
     * GET /api/tienda/pedidos?filtro=pendiente_pago&page=1
     */
    public function index(Request $request): JsonResponse
    {
        $cliente = $this->clienteAutenticado();
        $filtro  = $request->string('filtro')->toString() ?: PedidoClienteService::FILTRO_TODOS;

        $pedidos = $this->servicio->query($cliente, $filtro)->paginate(10);

        return response()->json([
            'data' => PedidoClienteListaResource::collection($pedidos->items()),
            'meta' => [
                'current_page' => $pedidos->currentPage(),
                'last_page'    => $pedidos->lastPage(),
                'total'        => $pedidos->total(),
            ],
        ]);
    }

    /**
     * GET /api/tienda/pedidos/{numero}
     */
    public function show(string $numero): JsonResponse
    {
        $cliente = $this->clienteAutenticado();
        $pedido  = $this->servicio->porNumero($cliente, $numero);

        return response()->json(['data' => new PedidoClienteDetalleResource($pedido)]);
    }

    /**
     * GET /api/tienda/pedidos/pendientes
     *
     * Alimenta el punto rojo sobre el ícono de perfil (cualquier página),
     * la tarjeta flotante que se abre sola en la Home, y el contador del
     * ítem "Mis pedidos" del dropdown. Un array vacío cuando no hay
     * ninguno: el frontend simplemente no muestra nada, no es un error.
     */
    public function pendientes(): JsonResponse
    {
        $cliente = $this->clienteAutenticado();
        $pedidos = $this->servicio->pendientes($cliente);

        return response()->json([
            'data'  => PedidoPendienteResource::collection($pedidos),
            'total' => $pedidos->count(),
        ]);
    }

    /**
     * GET /api/tienda/pedidos/{numero}/pagos/{pago}/comprobante
     *
     * Mismo criterio que el endpoint equivalente del Panel Admin: se sirve
     * por la API en vez de exponer /storage, para que el archivo quede
     * protegido por autenticación y no por oscuridad de la URL. La
     * diferencia es el scope: acá sólo el dueño del pedido puede pedirlo,
     * nunca por permiso de staff.
     */
    public function comprobante(string $numero, int $pago): StreamedResponse
    {
        $cliente = $this->clienteAutenticado();
        $pedido  = $this->servicio->porNumero($cliente, $numero);

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

    private function clienteAutenticado(): Cliente
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona.cliente');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        $cliente = $usuario->persona?->cliente;
        abort_if(!$cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        return $cliente;
    }
}