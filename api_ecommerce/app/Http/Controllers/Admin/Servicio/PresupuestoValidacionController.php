<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\DevolverPresupuestoRequest;
use App\Http\Resources\Servicio\PresupuestoListaResource;
use App\Http\Resources\Servicio\PresupuestoResource;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\PresupuestoServicio;
use App\Services\Servicio\PresupuestoService;
use App\Services\Servicio\StockServicioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Control de precios: el admin valida el presupuesto del técnico antes de
 * que llegue al cliente (o lo devuelve con una observación).
 */
class PresupuestoValidacionController extends Controller
{
    public function __construct(
        private PresupuestoService $presupuestos,
        private StockServicioService $stock,
    ) {}

    // GET /api/admin/servicios/presupuestos?estado=EN_REVISION&buscar=
    public function index(Request $request): JsonResponse
    {
        $estado = $request->input('estado', EstadoPresupuesto::EN_REVISION);

        $lista = PresupuestoServicio::query()
            ->when($estado, fn ($q) => $q->whereHas('estado', fn ($e) => $e->where('epr_codigo', $estado)))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $like = '%' . mb_strtolower($request->buscar) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(pse_numero) LIKE ?', [$like])
                    ->orWhereHas('orden', fn ($o) => $o->whereRaw('LOWER(ose_numero) LIKE ?', [$like]))
                    ->orWhereHas('orden.solicitud', fn ($o) => $o->whereRaw('LOWER(sse_numero) LIKE ?', [$like])));
            })
            ->with([
                'estado', 'tecnico.persona',
                'orden:ose_id,ose_numero,ose_sseid',
                'orden.solicitud:sse_id,sse_numero,sse_cliid,sse_tdiid,sse_marca,sse_modelo',
                'orden.solicitud.cliente.persona', 'orden.solicitud.tipoDispositivo',
            ])
            ->orderBy('pse_id')
            ->get();

        return response()->json([
            'data' => PresupuestoListaResource::collection($lista),
            'meta' => ['total' => $lista->count()],
        ], 200);
    }

    // GET /api/admin/servicios/presupuestos/{id}
    public function show(int $id): JsonResponse
    {
        $p = $this->cargar($id);

        return response()->json(['data' => $this->serializar($p)], 200);
    }

    // POST /api/admin/servicios/presupuestos/{id}/validar
    public function validar(int $id): JsonResponse
    {
        $this->presupuestos->validarYEnviar($this->cargar($id), auth('api')->user());

        return response()->json([
            'message' => 'Presupuesto validado y enviado al cliente',
            'data'    => $this->serializar($this->cargar($id)),
        ], 200);
    }

    // POST /api/admin/servicios/presupuestos/{id}/devolver
    public function devolver(DevolverPresupuestoRequest $request, int $id): JsonResponse
    {
        $this->presupuestos->devolverAlTecnico($this->cargar($id), $request->validated('obs'), auth('api')->user());

        return response()->json([
            'message' => 'Presupuesto devuelto al técnico',
            'data'    => $this->serializar($this->cargar($id)),
        ], 200);
    }

    // ---------- Auxiliares ----------

    private function cargar(int $id): PresupuestoServicio
    {
        $p = PresupuestoServicio::with([
            'estado', 'detalles', 'tecnico.persona',
            'orden.diagnostico.fallas.falla',
            'orden.solicitud.cliente.persona', 'orden.solicitud.tipoDispositivo',
        ])->findOrFail($id);

        $this->stock->anotarReferencias($p);

        return $p;
    }

    /** Presupuesto + contexto mínimo para decidir (equipo y diagnóstico). */
    private function serializar(PresupuestoServicio $p): array
    {
        $s   = $p->orden->solicitud;
        $dia = $p->orden->diagnostico;

        return [
            'presupuesto' => new PresupuestoResource($p),
            'ose_numero'  => $p->orden->ose_numero,
            'sse_numero'  => $s->sse_numero,
            'sse_garant'  => $s->sse_garant,
            'cliente'     => $s->cliente?->persona?->nombre_completo,
            'equipo'      => trim(($s->tipoDispositivo?->tdi_descri ?? '') . ' ' . $s->sse_marca . ' ' . $s->sse_modelo),
            'tecnico'     => $p->tecnico?->persona?->nombre_completo,
            'diagnostico' => $dia ? [
                'causa'  => $dia->dia_causa,
                'tiempo' => $dia->dia_tiempo,
                'fallas' => $dia->fallas->map(fn ($f) => $f->falla?->fll_descri)->filter()->values(),
            ] : null,
        ];
    }
}
