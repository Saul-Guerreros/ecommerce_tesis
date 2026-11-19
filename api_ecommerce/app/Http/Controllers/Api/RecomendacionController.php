<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recomendacion\RecomendacionRequest;
use App\Models\Cliente;
use App\Services\Comportamiento\ActividadClienteService;
use App\Services\Recomendacion\RecomendacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Recomendaciones públicas (invitado o cliente con JWT).
 *
 * Responsabilidades:
 *  - Identificar al visitante (concierne a HTTP: JWT + header X-Visitante).
 *  - Delegar el ranking a RecomendacionService.
 *  - Serializar con el mismo formato de tarjeta de la vitrina
 *    (VitrinaController::productosPorIds, igual que FavoritoController).
 *
 * Contrato: { data: [...], meta: { estrategia, total } } — 200.
 * Slug inexistente: { message } — 404 (ModelNotFoundException).
 */
class RecomendacionController extends Controller
{
    private const ROL_CLIENTE = 'Cliente';

    public function __construct(
        private readonly RecomendacionService $recomendaciones,
        private readonly VitrinaController $vitrina,
    ) {
    }

    // GET /api/tienda/recomendaciones/para-ti?limite=8
    public function paraTi(RecomendacionRequest $request): JsonResponse
    {
        [$cliId, $visita] = $this->identificar($request);

        return $this->responder($this->recomendaciones->paraVisitante($cliId, $visita), $request->limite());
    }

    // GET /api/tienda/recomendaciones/producto/{slug}?limite=8
    public function producto(RecomendacionRequest $request, string $slug): JsonResponse
    {
        return $this->responder($this->recomendaciones->similaresA($slug, $request->limite()), $request->limite());
    }

    // GET /api/tienda/recomendaciones/tendencias?limite=8
    public function tendencias(RecomendacionRequest $request): JsonResponse
    {
        return $this->responder($this->recomendaciones->tendenciasPublicas(), $request->limite());
    }

    // ------------------------------------------------------------------

    /** @param array{estrategia: string, ids: int[]} $resultado */
    private function responder(array $resultado, int $limite): JsonResponse
    {
        // Se piden más candidatos de los necesarios porque los sin stock se descartan.
        $productos = $this->vitrina->productosPorIds(array_slice($resultado['ids'], 0, $limite * 3))
            ->reject(fn (array $p) => $p['controla_stock'] && $p['stock'] <= 0)
            ->take($limite)
            ->values();

        return response()->json([
            'data' => $productos,
            'meta' => [
                'estrategia' => $resultado['estrategia'],
                'total'      => $productos->count(),
            ],
        ]);
    }

    /** @return array{0: ?int, 1: ?string} [cli_id, uuid visitante]. El personal se trata como anónimo. */
    private function identificar(Request $request): array
    {
        $visita = $request->header(ActividadClienteService::HEADER_VISITANTE);
        $visita = is_string($visita) && Str::isUuid($visita) ? strtolower($visita) : null;

        $usuario = auth('api')->user();
        if (! $usuario || $usuario->rol?->rol_descri !== self::ROL_CLIENTE) {
            return [null, $visita];
        }

        $cliId = Cliente::where('cli_perid', $usuario->usu_perid)->value('cli_id');

        return [$cliId ? (int) $cliId : null, $visita];
    }
}