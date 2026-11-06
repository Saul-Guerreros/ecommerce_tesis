<?php

namespace App\Http\Controllers\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\ReemplazarComprobanteRequest;
use App\Http\Requests\Servicio\PagoServicioRequest;
use App\Http\Resources\Pedido\MetodoPagoResource;
use App\Http\Resources\Servicio\ServicioPagoClienteResource;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Services\Servicio\PagoServicioService;
use App\Services\Servicio\SolicitudServicioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * C4 - Pago del servicio desde la tienda. Espejo de PagoPedidoController:
 * el recurso es la solicitud identificada por su número en la URL, así la
 * pantalla es reanudable y solo opera sobre servicios del cliente.
 */
class PagoServicioController extends Controller
{
    public function __construct(
        private PagoServicioService $pagos,
        private SolicitudServicioService $solicitudes,
    ) {}

    // GET /api/tienda/servicios/{numero}/pago
    public function config(string $numero): JsonResponse
    {
        $d = $this->pagos->datosIniciales($this->clienteAutenticado(), $numero);

        return response()->json([
            'servicio'            => ['sse_numero' => $numero],
            'resumen'             => $d['resumen'],
            'metodos'             => MetodoPagoResource::collection($d['metodos']),
            'datos_transferencia' => $d['datos_transferencia'],
            'datos_billetera'     => $d['datos_billetera'],
            'mensaje_efectivo'    => $d['mensaje_efectivo'],
            'pagos'               => $this->pagosCliente($d['pagos'], $numero),
        ], 200);
    }

    // POST /api/tienda/servicios/{numero}/pago
    public function registrar(PagoServicioRequest $request, string $numero): JsonResponse
    {
        $s = $this->pagos->registrar($this->clienteAutenticado(), $numero, $this->conArchivos($request));

        return $this->respuesta('Recibimos tu pago. Te avisamos apenas lo verifiquemos.', $s, 201);
    }

    // POST /api/tienda/servicios/{numero}/pagos/{pago}/comprobante
    public function reemplazarComprobante(ReemplazarComprobanteRequest $request, string $numero, int $pago): JsonResponse
    {
        $s = $this->pagos->reemplazarComprobante($this->clienteAutenticado(), $numero, $pago, $request->file('comprobante'));

        return $this->respuesta('Comprobante actualizado. Lo vamos a revisar nuevamente.', $s);
    }

    // GET /api/tienda/servicios/{numero}/pagos/{pago}/comprobante  — solo de SUS servicios
    public function comprobante(string $numero, int $pago): StreamedResponse
    {
        $s        = $this->solicitudes->detalleDelCliente($this->clienteAutenticado(), $numero);
        $registro = ServicioPago::where('spa_id', $pago)->where('spa_sseid', $s->sse_id)->firstOrFail();
        $archivo  = $registro->comprobante?->sco_archiv;

        abort_if(!$archivo || !Storage::disk('public')->exists($archivo), 404, 'El comprobante no está disponible.');

        return Storage::disk('public')->response($archivo, basename($archivo));
    }

    // ---------- Helpers ----------

    /** Los archivos se toman con file() para no depender de cómo validated() arma los anidados. */
    private function conArchivos(PagoServicioRequest $request): array
    {
        $datos = $request->validated();

        if (isset($datos['pagos'])) {
            foreach ($datos['pagos'] as $i => $p) {
                $datos['pagos'][$i]['comprobante'] = $request->file("pagos.$i.comprobante");
            }
        } else {
            $datos['comprobante'] = $request->file('comprobante');
        }

        return $datos;
    }

    private function respuesta(string $mensaje, SolicitudServicio $s, int $status = 200): JsonResponse
    {
        $s->loadMissing(['pagos.motivoRechazo', 'estadoPago']);

        return response()->json([
            'message' => $mensaje,
            'data'    => [
                'estado_pago' => $s->estadoPago?->epa_descri,
                'pagos'       => $this->pagosCliente($s->pagos, $s->sse_numero),
            ],
        ], $status);
    }

    private function pagosCliente($pagos, string $numero): array
    {
        return $pagos->map(fn ($p) => (new ServicioPagoClienteResource($p, $numero))->resolve())->values()->all();
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona.cliente');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}
