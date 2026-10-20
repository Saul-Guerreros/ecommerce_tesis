<?php

namespace App\Http\Controllers\Admin\Pedido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\PedidoIndexRequest;
use App\Http\Resources\Pedido\PedidoListaResource;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\TipoEntrega;
use App\Services\Pedido\PedidoConsultaService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bandeja de pedidos del Panel Admin (solo lectura).
 *
 * El detalle del pedido, la verificacion de pagos y el cambio de estado
 * se implementan en una etapa posterior: este controlador no modifica
 * ningun registro.
 *
 * Protegido por 'permiso:pedidos.ver' / 'permiso:pedidos.exportar'
 * (ver routes/api.php y RolPermisoSeeder).
 */
class PedidoController extends Controller
{
    public function __construct(
        private PedidoConsultaService $consulta,
    ) {}

    /**
     * GET /api/admin/pedidos
     * Soporta: ?num= ?ci= ?cliente= ?estado_pedido= ?estado_pago=
     *          ?tipo_entrega= ?metodo_pago= ?desde= ?hasta= ?page= ?per_page=
     */
    public function index(PedidoIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $perPage = (int) ($filtros['per_page'] ?? 20);

        $pedidos = $this->consulta->query($filtros)->paginate($perPage);

        return response()->json([
            'data' => PedidoListaResource::collection($pedidos->items()),
            'meta' => [
                'current_page' => $pedidos->currentPage(),
                'last_page'    => $pedidos->lastPage(),
                'per_page'     => $pedidos->perPage(),
                'total'        => $pedidos->total(),

                // Para "Mostrando 1-20 de 147" sin que Angular recalcule.
                'desde' => $pedidos->firstItem() ?? 0,
                'hasta' => $pedidos->lastItem() ?? 0,

                // Contador global de la alerta: NO depende de los filtros
                // aplicados, porque mide trabajo pendiente total, y viaja
                // junto al listado para que se refresque en cada carga.
                'pagos_pendientes'   => $this->consulta->pagosPendientes(),
                'retiros_vencidos'   => $this->consulta->retirosVencidos(),
            ],
        ]);
    }

    /**
     * GET /api/admin/pedidos/config
     * Combos de los filtros. Se devuelven solo los registros activos: no
     * tiene sentido ofrecer un metodo de pago deshabilitado como si
     * estuviera disponible.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'estados_pedido' => EstadoPedido::where('est_estado', 1)
                ->orderBy('est_id')
                ->get(['est_id', 'est_codigo', 'est_descri']),

            'estados_pago' => EstadoPago::where('epa_estado', 1)
                ->orderBy('epa_id')
                ->get(['epa_id', 'epa_codigo', 'epa_descri']),

            'tipos_entrega' => TipoEntrega::where('ten_estado', 1)
                ->orderBy('ten_id')
                ->get(['ten_id', 'ten_codigo', 'ten_descri']),

            'metodos_pago' => MetodoPago::activos()
                ->orderBy('mpa_id')
                ->get(['mpa_id', 'mpa_codigo', 'mpa_descri']),
        ]);
    }

    /**
     * GET /api/admin/pedidos/exportar
     *
     * Exporta EXACTAMENTE los pedidos que coinciden con los filtros y la
     * busqueda aplicados, reutilizando la misma consulta del listado.
     *
     * Se genera CSV con streamDownload y lazy() para no cargar todos los
     * pedidos en memoria: un comercio con miles de pedidos agotaria el
     * limite de PHP si se usara get().
     */
    public function exportar(PedidoIndexRequest $request): StreamedResponse|JsonResponse
    {
        $filtros = $request->validated();
        $query   = $this->consulta->query($filtros);

        if ($query->clone()->count() === 0) {
            return response()->json([
                'message' => 'No hay pedidos para exportar con los filtros aplicados.',
            ], 422);
        }

        $nombre = 'pedidos_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8: sin esto Excel abre las tildes y la ñ como simbolos.
            fwrite($salida, "\xEF\xBB\xBF");

            // Separador ';' porque Excel en configuracion regional ES usa
            // la coma como separador decimal.
            fputcsv($salida, [
                'N° Pedido',
                'Fecha',
                'Cliente',
                'Cédula',
                'Tipo de entrega',
                'Total (Gs.)',
                'Método de pago',
                'Verificación del pago',
                'Estado del pedido',
            ], ';');

            foreach ($query->lazy(200) as $pedido) {
                $fila = (new PedidoListaResource($pedido))->resolve();

                // Se exporta solo lo visible en la bandeja. Las observaciones
                // internas, los comprobantes y los datos de facturacion NO
                // salen en el archivo.
                fputcsv($salida, [
                    $fila['numero'],
                    $fila['fecha_completa'],
                    $fila['cliente']['nombre'],
                    $fila['cliente']['cedula'],
                    $fila['entrega']['etiqueta'],
                    number_format($fila['total'], 0, ',', '.'),
                    $fila['metodo_pago']['etiqueta'],
                    trim(
                        ($fila['verificacion_pago']['descripcion'] ?? '')
                        . ($fila['verificacion_pago']['detalle'] ? ' (' . $fila['verificacion_pago']['detalle'] . ')' : '')
                    ),
                    $fila['estado_pedido']['descripcion'],
                ], ';');
            }

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}