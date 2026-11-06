<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\FacturacionServicioRequest;
use App\Http\Requests\Servicio\ReenviarFacturaServicioRequest;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\FacturacionServicioService;
use App\Services\Servicio\FacturaServicioConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Facturación del servicio en el panel:
 *   - Datos fiscales que el cajero carga antes de "Registrar cobro".
 *   - Sección "Factura" del detalle: estado SIFEN, KuDE, XML, reintentar y reenviar.
 *
 * Los permisos se exigen en routes/api.php. El controlador no persiste:
 * delega en los services de app/Services/Servicio.
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

    // GET /api/admin/servicios/cobros/{id}/factura
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->consulta->resumenPanel(SolicitudServicio::findOrFail($id))], 200);
    }

    // GET /api/admin/servicios/cobros/{id}/factura/kude
    public function kude(int $id): StreamedResponse
    {
        return $this->archivo($id, 'pdf', 'inline');
    }

    // GET /api/admin/servicios/cobros/{id}/factura/xml
    public function xml(int $id): StreamedResponse
    {
        return $this->archivo($id, 'xml', 'attachment');
    }

    // POST /api/admin/servicios/cobros/{id}/factura/reintentar
    public function reintentar(int $id): JsonResponse
    {
        $this->consulta->reintentar(SolicitudServicio::findOrFail($id));

        return response()->json(['message' => 'Reintento en curso. El estado se actualiza en unos segundos.'], 202);
    }

    // POST /api/admin/servicios/cobros/{id}/factura/reenviar
    public function reenviar(ReenviarFacturaServicioRequest $request, int $id): JsonResponse
    {
        $s     = SolicitudServicio::findOrFail($id);
        $datos = $request->validated();

        try {
            $destino = $this->consulta->reenviar($s, $datos['canal'] ?? null, $datos['destino'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => "Factura enviada a {$destino}.",
            'data'    => $this->consulta->resumenPanel($s),
        ], 200);
    }

    // ---------- Helpers ----------

    /** El panel manda el JWT en una cabecera: el front pide el archivo como Blob. */
    private function archivo(int $id, string $tipo, string $disposicion): StreamedResponse
    {
        $a = $this->consulta->archivo(SolicitudServicio::findOrFail($id), $tipo);

        return Storage::disk('local')->response($a['ruta'], $a['nombre'], ['Content-Type' => $a['mime']], $disposicion);
    }
}
