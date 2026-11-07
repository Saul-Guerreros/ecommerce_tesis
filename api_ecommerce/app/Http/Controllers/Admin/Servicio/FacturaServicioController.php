<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\FacturacionServicioRequest;
use App\Http\Requests\Servicio\ReenviarFacturaServicioRequest;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Services\Servicio\FacturacionServicioService;
use App\Services\Servicio\FacturaServicioConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Servicio\AnularFacturaServicioRequest;

/**
 * Facturación del servicio en el panel: datos fiscales y las secciones
 * "Factura" y "Factura de anticipo" del detalle.
 *
 * $tipo llega por ->defaults('tipo', 'ANTICIPO') en las rutas /anticipo/*;
 * sin default, es la factura final.
 */
class FacturaServicioController extends Controller
{
    public function __construct(
        private FacturacionServicioService $datosFiscales,
        private FacturaServicioConsultaService $consulta,
    ) {}

    // GET /api/admin/servicios/cobros/{id}/facturacion
    public function datosFiscales(int $id): JsonResponse
    {
        return response()->json(['data' => $this->datosFiscales->opciones(SolicitudServicio::findOrFail($id))], 200);
    }

    // PUT /api/admin/servicios/cobros/{id}/facturacion
    public function guardarDatosFiscales(FacturacionServicioRequest $request, int $id): JsonResponse
    {
        $s = SolicitudServicio::findOrFail($id);
        $this->datosFiscales->actualizar($s, $request->validated());

        return response()->json([
            'message' => 'Datos de facturación guardados.',
            'data'    => $this->datosFiscales->opciones($s->fresh()),
        ], 200);
    }

    // GET .../{id}/factura   |   GET .../{id}/anticipo/factura
    public function show(int $id, string $tipo = VentaCab::TIPO_VENTA): JsonResponse
    {
        return response()->json(['data' => $this->consulta->resumenPanel(SolicitudServicio::findOrFail($id), $tipo)], 200);
    }

    // GET .../factura/kude   |   GET .../anticipo/factura/kude
    public function kude(int $id, string $tipo = VentaCab::TIPO_VENTA): StreamedResponse
    {
        return $this->archivo($id, 'pdf', 'inline', $tipo);
    }

    // GET .../factura/xml   |   GET .../anticipo/factura/xml
    public function xml(int $id, string $tipo = VentaCab::TIPO_VENTA): StreamedResponse
    {
        return $this->archivo($id, 'xml', 'attachment', $tipo);
    }

    // POST .../factura/reintentar   |   POST .../anticipo/factura/reintentar
    public function reintentar(int $id, string $tipo = VentaCab::TIPO_VENTA): JsonResponse
    {
        $this->consulta->reintentar(SolicitudServicio::findOrFail($id), $tipo);

        return response()->json(['message' => 'Reintento en curso. El estado se actualiza en unos segundos.'], 202);
    }

    // POST .../factura/reenviar   |   POST .../anticipo/factura/reenviar
    public function reenviar(ReenviarFacturaServicioRequest $request, int $id, string $tipo = VentaCab::TIPO_VENTA): JsonResponse
    {
        $s     = SolicitudServicio::findOrFail($id);
        $datos = $request->validated();

        try {
            $destino = $this->consulta->reenviar($s, $datos['canal'] ?? null, $datos['destino'] ?? null, $tipo);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => "Factura enviada a {$destino}.",
            'data'    => $this->consulta->resumenPanel($s, $tipo),
        ], 200);
    }

        // POST .../factura/anular   |   POST .../anticipo/factura/anular
    public function anular(AnularFacturaServicioRequest $request, int $id, string $tipo = VentaCab::TIPO_VENTA): JsonResponse
    {
        $venta = $this->consulta->anular(
            SolicitudServicio::findOrFail($id),
            $request->validated()['motivo'],
            auth('api')->user(),
            $tipo,
        );

        return response()->json([
            'message' => ($venta->esAnticipo() ? 'Factura de anticipo ' : 'Factura ')
                       . "{$venta->ven_nrodoc} anulada. Ya se puede volver a emitir.",
        ], 200);
    }

    // ---------- Helpers ----------

    private function archivo(int $id, string $formato, string $disposicion, string $tipo): StreamedResponse
    {
        $a = $this->consulta->archivo(SolicitudServicio::findOrFail($id), $formato, $tipo);

        return Storage::disk('local')->response($a['ruta'], $a['nombre'], ['Content-Type' => $a['mime']], $disposicion);
    }
}