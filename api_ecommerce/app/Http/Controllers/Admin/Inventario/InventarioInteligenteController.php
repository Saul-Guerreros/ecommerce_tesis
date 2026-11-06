<?php
// app/Http/Controllers/Admin/Inventario/InventarioInteligenteController.php
namespace App\Http\Controllers\Admin\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\CerrarAlertaRequest;
use App\Http\Requests\Inventario\ConfigInventarioRequest;
use App\Http\Requests\Inventario\FiltroAlertasRequest;
use App\Http\Requests\Inventario\LeadTimeRequest;
use App\Models\Inventario\AlertaStock;
use App\Services\Inventario\InventarioInteligenteService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Inventario\InsightsInventarioService;

/**
 * Gestión inteligente de inventario: predicción de demanda y alertas de reposición.
 * Respuesta: { data, meta? } igual que el resto del panel.
 */
class InventarioInteligenteController extends Controller
{
    public function __construct(private readonly InventarioInteligenteService $servicio)
    {
    }

    /** GET /api/admin/inventario-inteligente/resumen */
    public function resumen(): JsonResponse
    {
        return response()->json(['data' => $this->servicio->resumen()]);
    }

    /** GET /api/admin/inventario-inteligente/notificaciones */
    public function notificaciones(): JsonResponse
    {
        return response()->json(['data' => $this->servicio->notificaciones()]);
    }

    /** GET /api/admin/inventario-inteligente/alertas?estado=1&tipo=&deposito=&search= */
    public function alertas(FiltroAlertasRequest $request): JsonResponse
    {
        $data = $this->servicio->alertas($request->validated());

        return response()->json(['data' => $data, 'meta' => ['total' => count($data)]]);
    }

    /** PATCH /api/admin/inventario-inteligente/alertas/{id}/atender */
    public function atender(CerrarAlertaRequest $request, int $id): JsonResponse
    {
        return $this->cerrar($request, $id, AlertaStock::ESTADO_ATENDIDA, 'Alerta marcada como atendida.');
    }

    /** PATCH /api/admin/inventario-inteligente/alertas/{id}/descartar */
    public function descartar(CerrarAlertaRequest $request, int $id): JsonResponse
    {
        return $this->cerrar($request, $id, AlertaStock::ESTADO_DESCARTADA, 'Alerta descartada.');
    }

    /** GET /api/admin/inventario-inteligente/productos/{id}/pronostico?deposito= */
    public function pronostico(Request $request, int $id): JsonResponse
    {
        $request->validate(['deposito' => ['sometimes', 'nullable', 'integer', 'exists:deposito,dpo_id']]);

        return response()->json([
            'data' => $this->servicio->pronosticoProducto($id, $request->integer('deposito') ?: null),
        ]);
    }

    /** PATCH /api/admin/inventario-inteligente/productos/{id}/lead-time */
    public function leadTime(LeadTimeRequest $request, int $id): JsonResponse
    {
        $this->servicio->actualizarLeadTime($id, $request->input('lead_time'));

        return response()->json(['message' => 'Lead time actualizado y alertas recalculadas.']);
    }

    /** GET /api/admin/inventario-inteligente/metricas */
    public function metricas(): JsonResponse
    {
        return response()->json(['data' => $this->servicio->metricas()]);
    }

    /** GET /api/admin/inventario-inteligente/config */
    public function config(): JsonResponse
    {
        return response()->json(['data' => $this->servicio->config()]);
    }

    /** PUT /api/admin/inventario-inteligente/config */
    public function actualizarConfig(ConfigInventarioRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Parámetros guardados y alertas recalculadas.',
            'data'    => $this->servicio->actualizarConfig($request->columnas()),
        ]);
    }

    /** POST /api/admin/inventario-inteligente/recalcular */
    public function recalcular(): JsonResponse
    {
        return response()->json([
            'message' => 'Pronóstico y alertas recalculados.',
            'data'    => $this->servicio->recalcular(),
        ]);
    }

        /** POST /api/admin/inventario-inteligente/insights  { forzar?: bool } */
    public function insights(Request $request, InsightsInventarioService $insights): JsonResponse
    {
        $request->validate(['forzar' => ['sometimes', 'boolean']]);

        if (! $insights->disponible()) {
            return response()->json(['message' => 'La IA no está configurada en el servidor.'], 503);
        }

        $resultado = $insights->generar($request->boolean('forzar'));

        if (! $resultado) {
            return response()->json(['message' => 'La IA no respondió. Intentá de nuevo en unos minutos.'], 503);
        }

        return response()->json(['data' => $resultado]);
    }

    private function cerrar(CerrarAlertaRequest $request, int $id, int $estado, string $mensaje): JsonResponse
    {
        try {
            $alerta = $this->servicio->cerrarAlerta($id, $estado, (int) auth()->id(), $request->input('observacion'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => $mensaje, 'data' => ['id' => $alerta->ast_id, 'estado' => $alerta->ast_estado]]);
    }
}