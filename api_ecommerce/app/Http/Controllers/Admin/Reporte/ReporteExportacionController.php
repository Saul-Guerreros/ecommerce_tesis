<?php

namespace App\Http\Controllers\Admin\Reporte;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteInventarioRequest;
use App\Http\Requests\Reporte\ReporteVentasRequest;
use App\Services\Reporte\Exportacion\ExportacionReporteService;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Reporte\ReporteComprasRequest;
use App\Http\Requests\Reporte\ReporteServicioRequest;
use App\Http\Requests\Reporte\FiltroReporteRequest;
use App\Http\Requests\Reporte\ReporteClientesRequest;
use App\Http\Requests\Reporte\ReporteChatbotRequest;

/**
 * Exportación PDF/Excel. Un método por reporte para reutilizar su
 * FormRequest (mismos filtros y validaciones que la pantalla).
 */
class ReporteExportacionController extends Controller
{
    public function __construct(private readonly ExportacionReporteService $exportacion)
    {
    }

    /** GET /api/admin/reportes/ventas/{vista}/exportar?formato=pdf|excel */
    public function ventas(ReporteVentasRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('ventas', $vista, $request->formato(), $request->filtros(), [
            'categoria_id' => $request->categoriaId(),
            'marca_id'     => $request->marcaId(),
            'por'          => $request->por(),
        ]);
    }

    /** GET /api/admin/reportes/inventario/{vista}/exportar?formato=pdf|excel */
    public function inventario(ReporteInventarioRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('inventario', $vista, $request->formato(), $request->filtros(), [
            'categoria_id' => $request->categoriaId(),
            'marca_id'     => $request->marcaId(),
            'dias'         => $request->dias(),
        ]);
    }

    /** GET /api/admin/reportes/compras/{vista}/exportar?formato=pdf|excel */
    public function compras(ReporteComprasRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('compras', $vista, $request->formato(), $request->filtros(), [
            'proveedor_id' => $request->proveedorId(),
            'dias'         => $request->dias(),
        ]);
    }

    /** GET /api/admin/reportes/servicio/{vista}/exportar?formato=pdf|excel */
    public function servicio(ReporteServicioRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('servicio', $vista, $request->formato(), $request->filtros(), [
            'tipo_dispositivo_id' => $request->tipoDispositivoId(),
        ]);
    }

     /** GET /api/admin/reportes/clientes/{vista}/exportar?formato=pdf|excel */
    public function clientes(ReporteClientesRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('clientes', $vista, $request->formato(), $request->filtros(), [
            'tipo_cliente_id' => $request->tipoClienteId(),
            'dias'            => $request->dias(),
        ]);
    }

    /** GET /api/admin/reportes/promociones/{vista}/exportar?formato=pdf|excel */
    public function promociones(FiltroReporteRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('promociones', $vista, $request->formato(), $request->filtros(), []);
    }

    /** GET /api/admin/reportes/chatbot/{vista}/exportar?formato=pdf|excel */
    public function chatbot(ReporteChatbotRequest $request, string $vista): Response
    {
        return $this->exportacion->descargar('chatbot', $vista, $request->formato(), $request->filtros(), [
            'tipo' => $request->tipo(),
        ]);
    }


}