<?php

namespace App\Http\Controllers\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\FacturacionServicioRequest;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Services\Servicio\FacturacionServicioService;
use App\Services\Servicio\FacturaServicioConsultaService;
use App\Services\Servicio\SolicitudServicioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * C4 - Facturación del servicio desde la tienda: el cliente elige CI o RUC
 * antes de pagar y descarga su factura cuando la DNIT la aprueba.
 * Solo opera sobre servicios del cliente autenticado (detalleDelCliente).
 */
class FacturaServicioClienteController extends Controller
{
    public function __construct(
        private FacturacionServicioService $datosFiscales,
        private FacturaServicioConsultaService $consulta,
        private SolicitudServicioService $solicitudes,
    ) {}

    // GET /api/tienda/servicios/{numero}/facturacion
    public function show(string $numero): JsonResponse
    {
        $s = $this->solicitud($numero);

        return response()->json([
            'data' => $this->datosFiscales->opciones($s) + ['factura' => $this->consulta->resumenCliente($s)],
        ], 200);
    }

    // PUT /api/tienda/servicios/{numero}/facturacion
    public function update(FacturacionServicioRequest $request, string $numero): JsonResponse
    {
        $s = $this->solicitud($numero);
        $this->datosFiscales->actualizar($s, $request->validated());

        return response()->json([
            'message' => 'Datos de facturación guardados.',
            'data'    => $this->datosFiscales->opciones($s->fresh()),
        ], 200);
    }

    // GET /api/tienda/servicios/{numero}/factura/kude
    public function kude(string $numero): StreamedResponse
    {
        $s = $this->solicitud($numero);

        abort_if(!($this->consulta->resumenCliente($s)['disponible'] ?? false), 404, 'La factura todavía no está disponible.');

        $a = $this->consulta->archivo($s, 'pdf');

        return Storage::disk('local')->response($a['ruta'], $a['nombre'], ['Content-Type' => $a['mime']], 'inline');
    }

    // ---------- Helpers ----------

    private function solicitud(string $numero): SolicitudServicio
    {
        return $this->solicitudes->detalleDelCliente($this->clienteAutenticado(), $numero);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona.cliente');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}
