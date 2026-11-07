<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\RechazarPagoRequest;
use App\Http\Requests\Pedido\ValidarComprobanteRequest;
use App\Http\Requests\Servicio\AnticipoMostradorRequest;
use App\Http\Requests\Servicio\CobrarEntregarRequest;
use App\Http\Requests\Servicio\CobroServicioIndexRequest;
use App\Http\Requests\Servicio\GenerarFacturaServicioRequest;
use App\Http\Requests\Servicio\RegistrarCobroServicioRequest;
use App\Http\Resources\Servicio\CobroServicioListaResource;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Services\Servicio\CobroMostradorService;
use App\Services\Servicio\CobroServicioConsultaService;
use App\Services\Servicio\FacturaServicioService;
use App\Services\Servicio\PagoServicioVerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Servicio\AnularCobroRequest;

/**
 * Cobro del servicio técnico en el panel. Ciclo comprobante -> factura ->
 * cobro (igual que PedidoGestionController):
 *
 *   validarComprobante()      pedidos.pago.verificar
 *   rechazar()                pedidos.pago.verificar (solo sin factura)
 *   declararAnticipo()        servicios.cobros.confirmar (mostrador)
 *   generarFacturaAnticipo()  cajero o verificador (anticipo web)
 *   generarFactura()          cajero o verificador (final)
 *   registrarCobro()          cajero o verificador (efectivo: cajero en sucursal)
 *   cobrarYEntregar()         A5
 *
 * Los permisos se exigen en routes/api.php; las reglas, en los services.
 */
class CobroServicioController extends Controller
{
    public function __construct(
        private CobroServicioConsultaService $consulta,
        private PagoServicioVerificacionService $verificacion,
        private CobroMostradorService $mostrador,
        private FacturaServicioService $facturas,
    ) {}

    // GET /api/admin/servicios/cobros/config
    public function config(): JsonResponse
    {
        return response()->json(['data' => $this->consulta->config(auth('api')->user())], 200);
    }

    // GET /api/admin/servicios/cobros
    public function index(CobroServicioIndexRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $pagina  = $this->consulta->query($filtros)->paginate((int) ($filtros['per_page'] ?? 20));

        return response()->json([
            'data' => CobroServicioListaResource::collection($pagina->items()),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'desde'        => $pagina->firstItem() ?? 0,
                'hasta'        => $pagina->lastItem() ?? 0,
            ],
        ], 200);
    }

    // GET /api/admin/servicios/cobros/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detalle($id)], 200);
    }

    // GET /api/admin/servicios/cobros/{id}/pagos/{pago}/comprobante
    public function comprobante(int $id, int $pago): StreamedResponse
    {
        $registro = ServicioPago::where('spa_id', $pago)->where('spa_sseid', $id)->firstOrFail();
        $archivo  = $registro->comprobante?->sco_archiv;

        abort_if(!$archivo, 404, 'Este pago no tiene comprobante cargado.');
        abort_if(!Storage::disk('public')->exists($archivo), 404, 'El archivo del comprobante ya no está disponible en el servidor.');

        return Storage::disk('public')->response($archivo, basename($archivo));
    }

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/comprobante/validar
    public function validarComprobante(ValidarComprobanteRequest $request, int $id, int $pago): JsonResponse
    {
        $this->verificacion->validarComprobante($this->solicitud($id), $pago, $request->validated(), auth('api')->user());

        return $this->respuesta('Comprobante validado. Ya se puede generar la factura y registrar el cobro.', $id);
    }

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/rechazar
    public function rechazar(RechazarPagoRequest $request, int $id, int $pago): JsonResponse
    {
        $this->verificacion->rechazar($this->solicitud($id), $pago, $request->validated(), auth('api')->user());

        return $this->respuesta('Pago rechazado.', $id);
    }

    // POST /api/admin/servicios/cobros/{id}/anticipo
    public function declararAnticipo(AnticipoMostradorRequest $request, int $id): JsonResponse
    {
        $venta = $this->mostrador->declararAnticipo($this->solicitud($id), $request->validated(), auth('api')->user());

        return $this->respuesta(
            "Factura de anticipo {$venta->ven_nrodoc} emitida. Registrá el cobro cuando su documento esté disponible.",
            $id, 201
        );
    }

    // POST /api/admin/servicios/cobros/{id}/anticipo/factura/generar
    public function generarFacturaAnticipo(int $id): JsonResponse
    {
        $venta = $this->facturas->generarAnticipo($this->solicitud($id), auth('api')->user());

        return $this->respuesta(
            "Factura de anticipo {$venta->ven_nrodoc} emitida. Registrá el cobro cuando su documento esté disponible.",
            $id, 201
        );
    }

    // POST /api/admin/servicios/cobros/{id}/factura/generar
    public function generarFactura(GenerarFacturaServicioRequest $request, int $id): JsonResponse
    {
        $venta = $this->mostrador->generarFactura($this->solicitud($id), $request->validated(), auth('api')->user());

        return $this->respuesta(
            $venta->ven_estado === VentaCab::ESTADO_COBRADA
                ? "Factura {$venta->ven_nrodoc} emitida: el anticipo cubre el total del servicio."
                : "Factura {$venta->ven_nrodoc} emitida. Registrá el cobro cuando su documento esté disponible.",
            $id, 201
        );
    }

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/cobrar
    public function registrarCobro(RegistrarCobroServicioRequest $request, int $id, int $pago): JsonResponse
    {
        $datos = $request->validated();
        $datos['comprobante'] = $request->file('comprobante');

        $this->verificacion->registrarCobro($this->solicitud($id), $pago, $datos, auth('api')->user());

        return $this->respuesta('Cobro registrado.', $id);
    }

        // PATCH /api/admin/servicios/cobros/{id}/cobros/{cobro}/anular
    public function anularCobro(AnularCobroRequest $request, int $id, int $cobro): JsonResponse
    {
        $this->verificacion->anularCobro($this->solicitud($id), $cobro, $request->validated()['motivo'], auth('api')->user());

        return $this->respuesta('Cobro anulado. El pago vuelve a quedar pendiente de cobro.', $id);
    }

    // POST /api/admin/servicios/cobros/{id}/cobrar-entregar  — A5
    public function cobrarYEntregar(CobrarEntregarRequest $request, int $id): JsonResponse
    {
        $datos = $request->validated();
        $cobro = $datos['cobro'] ?? [];
        $cobro['comprobante'] = $request->file('cobro.comprobante');
        unset($datos['cobro']);

        $this->mostrador->cobrarYEntregar($this->solicitud($id), $cobro, $datos, auth('api')->user());

        return response()->json(['message' => 'Cobro y entrega registrados. Puede imprimir la constancia de retiro.'], 201);
    }

    // ---------- Helpers ----------

    private function solicitud(int $id): SolicitudServicio
    {
        return SolicitudServicio::findOrFail($id);
    }

    /** Siempre lee de nuevo: el detalle refleja lo que quedó después del commit. */
    private function detalle(int $id): array
    {
        return $this->consulta->detalle($this->solicitud($id), auth('api')->user());
    }

    private function respuesta(string $mensaje, int $id, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'data' => $this->detalle($id)], $status);
    }
}