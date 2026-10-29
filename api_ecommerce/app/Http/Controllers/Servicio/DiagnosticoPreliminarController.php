<?php

namespace App\Http\Controllers\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\DiagnosticoPreliminarRequest;
use App\Http\Resources\Servicio\DiagnosticoPreliminarResource;
use App\Models\Servicio\DiagnosticoPreliminar;
use App\Services\Servicio\DiagnosticoPreliminarService;
use Illuminate\Http\JsonResponse;

/**
 * M5 - Diagnóstico preliminar con IA, en sus tres puntos de uso:
 * tienda (cliente), mostrador (funcionario) y orden del técnico (consulta).
 */
class DiagnosticoPreliminarController extends Controller
{
    public function __construct(private DiagnosticoPreliminarService $servicio) {}

    // POST /api/tienda/servicios/diagnostico-preliminar
    public function cliente(DiagnosticoPreliminarRequest $request): JsonResponse
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol');
        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $this->responder(
            $this->servicio->generar($usuario, $request->validated(), DiagnosticoPreliminar::CANAL_WEB)
        );
    }

    // POST /api/admin/servicios/solicitudes/mostrador/diagnostico-preliminar
    public function mostrador(DiagnosticoPreliminarRequest $request): JsonResponse
    {
        return $this->responder(
            $this->servicio->generar(auth('api')->user(), $request->validated(), DiagnosticoPreliminar::CANAL_MOSTRADOR)
        );
    }

    // GET /api/admin/servicios/tecnico/ordenes/{sseId}/diagnostico-preliminar
    public function tecnico(int $sseId): JsonResponse
    {
        $pre = DiagnosticoPreliminar::with(['fallas.falla', 'trabajos.trabajo'])
            ->where('dpr_sseid', $sseId)
            ->first();

        return response()->json(['data' => $pre ? new DiagnosticoPreliminarResource($pre) : null], 200);
    }

    private function responder(DiagnosticoPreliminar $pre): JsonResponse
    {
        return response()->json([
            'message' => $pre->generado()
                ? 'Diagnóstico preliminar generado.'
                : 'El diagnóstico preliminar no está disponible en este momento. Puede continuar con la solicitud normalmente.',
            'data' => new DiagnosticoPreliminarResource($pre),
        ], 201);
    }
}