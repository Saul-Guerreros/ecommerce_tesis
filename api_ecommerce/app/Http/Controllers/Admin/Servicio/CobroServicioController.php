<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\ConfirmarPagoRequest;
use App\Http\Requests\Pedido\RechazarPagoRequest;
use App\Http\Requests\Servicio\CobrarEntregarRequest;
use App\Http\Requests\Servicio\CobroMostradorRequest;
use App\Http\Requests\Servicio\CobroServicioIndexRequest;
use App\Http\Resources\Servicio\CobroServicioListaResource;
use App\Http\Resources\Servicio\ServicioPagoResource;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\CobroMostradorService;
use App\Services\Servicio\CobroServicioConsultaService;
use App\Services\Servicio\PagoServicioVerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cobro del servicio técnico en el panel:
 *   - Bandeja "Cobros de servicios" (servicios.cobros.ver).
 *   - Verificación de pagos web (pedidos.pago.verificar, decisión 14-b).
 *   - Cobro presencial y "Cobrar y entregar" (servicios.cobros.confirmar).
 *
 * Los permisos se exigen en routes/api.php. El controlador no persiste:
 * delega en los services de app/Services/Servicio.
 */
class CobroServicioController extends Controller
{
    public function __construct(
        private CobroServicioConsultaService $consulta,
        private PagoServicioVerificacionService $verificacion,
        private CobroMostradorService $mostrador,
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

    // GET /api/admin/servicios/cobros/{id}  — sección "Pagos" del detalle y de A5
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detalle(SolicitudServicio::findOrFail($id))], 200);
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

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/confirmar
    public function confirmar(ConfirmarPagoRequest $request, int $id, int $pago): JsonResponse
    {
        $s = $this->verificacion->confirmar(SolicitudServicio::findOrFail($id), $pago, $request->validated(), auth('api')->user());

        return $this->respuesta('Pago confirmado correctamente.', $s);
    }

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/rechazar
    public function rechazar(RechazarPagoRequest $request, int $id, int $pago): JsonResponse
    {
        $s = $this->verificacion->rechazar(SolicitudServicio::findOrFail($id), $pago, $request->validated(), auth('api')->user());

        return $this->respuesta('Pago rechazado. El cliente podrá subir un nuevo comprobante.', $s);
    }

    // POST /api/admin/servicios/cobros/{id}/cobros  — anticipo o saldo en el mostrador
    public function registrarCobro(CobroMostradorRequest $request, int $id): JsonResponse
    {
        $datos = $request->validated();
        $datos['comprobante'] = $request->file('comprobante');

        $s = $this->mostrador->registrarCobro(SolicitudServicio::findOrFail($id), $datos, auth('api')->user());

        return $this->respuesta('Cobro registrado.', $s, 201);
    }

    // POST /api/admin/servicios/cobros/{id}/pagos/{pago}/cobrar-al-retirar
    public function cobrarAlRetirar(int $id, int $pago): JsonResponse
    {
        $s = $this->mostrador->confirmarAlRetirar(SolicitudServicio::findOrFail($id), $pago, auth('api')->user());

        return $this->respuesta('Pago en efectivo cobrado.', $s);
    }

    // POST /api/admin/servicios/cobros/{id}/cobrar-entregar  — A5
    public function cobrarYEntregar(CobrarEntregarRequest $request, int $id): JsonResponse
    {
        $datos = $request->validated();
        $cobro = $datos['cobro'];
        $cobro['comprobante'] = $request->file('cobro.comprobante');
        unset($datos['cobro']);

        $this->mostrador->cobrarYEntregar(SolicitudServicio::findOrFail($id), $cobro, $datos, auth('api')->user());

        return response()->json(['message' => 'Cobro y entrega registrados. Puede imprimir la constancia de retiro.'], 201);
    }

    // ---------- Helpers ----------

    private function respuesta(string $mensaje, SolicitudServicio $s, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'data' => $this->detalle($s)], $status);
    }

    private function detalle(SolicitudServicio $s): array
    {
        $d = $this->consulta->detalle($s->fresh(), auth('api')->user());
        $d['pagos'] = ServicioPagoResource::collection($d['pagos']);

        return $d;
    }
}
