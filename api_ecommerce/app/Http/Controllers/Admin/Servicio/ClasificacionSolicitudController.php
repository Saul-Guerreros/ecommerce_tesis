<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\CorregirClasificacionRequest;
use App\Http\Resources\Servicio\ClasificacionSolicitudResource;
use App\Models\Servicio\CategoriaFalla;
use App\Models\Servicio\ClasificacionSolicitud;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\TipoDispositivo;
use App\Models\Servicio\UrgenciaServicio;
use App\Services\Servicio\ClasificacionSolicitudService;
use Illuminate\Http\JsonResponse;

/** Punto 7 - Consulta, corrección y reclasificación de la clasificación IA. */
class ClasificacionSolicitudController extends Controller
{
    private const RELACIONES = [
        'tipoDispositivo', 'tipoDispositivoCliente', 'tipoDispositivoIa', 'categoria', 'urgencia', 'fallaIa',
        'correcciones.usuario.persona',
    ];

    public function __construct(private ClasificacionSolicitudService $servicio) {}

    // GET /api/admin/servicios/clasificacion/config
    public function config(): JsonResponse
    {
        return response()->json([
            'tipos_dispositivo' => TipoDispositivo::seleccionables()->orderBy('tdi_descri')->get(['tdi_id', 'tdi_descri']),
            'categorias'        => CategoriaFalla::activas()->orderBy('caf_descri')->get(['caf_id', 'caf_codigo', 'caf_descri']),
            'urgencias'         => UrgenciaServicio::activas()->get(['urg_id', 'urg_codigo', 'urg_descri', 'urg_color', 'urg_criter']),
        ], 200);
    }

    // GET /api/admin/servicios/clasificacion/{sseId}
    public function show(int $sseId): JsonResponse
    {
        $cls = ClasificacionSolicitud::with(self::RELACIONES)->where('cls_sseid', $sseId)->first();

        return response()->json(['data' => $cls ? new ClasificacionSolicitudResource($cls) : null], 200);
    }

    // PUT /api/admin/servicios/clasificacion/{sseId}
    public function update(CorregirClasificacionRequest $request, int $sseId): JsonResponse
    {
        $cls = $this->servicio->corregir(SolicitudServicio::findOrFail($sseId), $request->validated(), auth('api')->user());

        return $this->responder('Clasificación corregida.', $cls);
    }

    // POST /api/admin/servicios/clasificacion/{sseId}/confirmar
    public function confirmar(int $sseId): JsonResponse
    {
        return $this->responder('Clasificación confirmada.', $this->servicio->confirmar(SolicitudServicio::findOrFail($sseId)));
    }

    // POST /api/admin/servicios/clasificacion/{sseId}/reclasificar
    public function reclasificar(int $sseId): JsonResponse
    {
        $this->servicio->reclasificar(SolicitudServicio::findOrFail($sseId));

        return response()->json(['message' => 'La solicitud se volvió a enviar a clasificar. Actualice en unos segundos.'], 202);
    }

    private function responder(string $mensaje, ClasificacionSolicitud $cls): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data'    => new ClasificacionSolicitudResource($cls->load(self::RELACIONES)),
        ], 200);
    }
}