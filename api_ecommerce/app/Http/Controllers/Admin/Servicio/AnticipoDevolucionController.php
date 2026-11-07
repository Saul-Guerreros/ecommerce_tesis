<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\AcreditarAnticipoRequest;
use App\Http\Requests\Servicio\AnularDevolucionRequest;
use App\Http\Requests\Servicio\DevolucionAnticipoRequest;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Servicio\AnticipoDevolucionService;
use App\Services\Servicio\CobroServicioConsultaService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Servicio\LiberarAnticipoRequest;

/**
 * Devolución del anticipo: NC (documento fiscal) y devolución del dinero
 * (hecho de caja). Responde siempre el detalle de cobro actualizado.
 */
class AnticipoDevolucionController extends Controller
{
    public function __construct(
        private AnticipoDevolucionService $devoluciones,
        private CobroServicioConsultaService $consulta,
    ) {}

    // POST /api/admin/servicios/cobros/{id}/anticipo/nota-credito
    public function acreditar(AcreditarAnticipoRequest $request, int $id): JsonResponse
    {
        $nota = $this->devoluciones->acreditar($this->solicitud($id), $request->validated(), auth('api')->user());

        return $this->respuesta(
            "Nota de crédito {$nota->ncv_nrodoc} emitida. Registrá la devolución cuando esté aprobada.",
            $id, 201
        );
    }

    // POST /api/admin/servicios/cobros/{id}/anticipo/notas-credito/{nota}/devoluciones
    public function devolver(DevolucionAnticipoRequest $request, int $id, int $nota): JsonResponse
    {
        $this->devoluciones->registrarDevolucion($this->solicitud($id), $nota, $request->validated(), auth('api')->user());

        return $this->respuesta('Devolución registrada.', $id, 201);
    }

    // PATCH /api/admin/servicios/cobros/{id}/anticipo/devoluciones/{devolucion}/anular
    public function anular(AnularDevolucionRequest $request, int $id, int $devolucion): JsonResponse
    {
        $this->devoluciones->anularDevolucion($this->solicitud($id), $devolucion, $request->validated()['motivo'], auth('api')->user());

        return $this->respuesta('Devolución anulada.', $id);
    }

    private function solicitud(int $id): SolicitudServicio
    {
        return SolicitudServicio::findOrFail($id);
    }

    // POST /api/admin/servicios/cobros/{id}/factura/anticipo/liberar
    public function liberar(LiberarAnticipoRequest $request, int $id): JsonResponse
    {
        $this->devoluciones->liberarAnticipo($this->solicitud($id), $request->validated()['motivo'], auth('api')->user());

        return $this->respuesta('Anticipo liberado: ya se puede acreditar con nota de crédito y devolver.', $id);
    }

    private function respuesta(string $mensaje, int $id, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data'    => $this->consulta->detalle($this->solicitud($id), auth('api')->user()),
        ], $status);
    }
}