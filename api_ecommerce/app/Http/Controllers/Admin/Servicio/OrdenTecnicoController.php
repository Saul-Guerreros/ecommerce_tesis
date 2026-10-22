<?php

namespace App\Http\Controllers\Admin\Servicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Servicio\CerrarNoReparableRequest;
use App\Http\Requests\Servicio\DiagnosticoRequest;
use App\Http\Requests\Servicio\FinalizarOrdenRequest;
use App\Http\Requests\Servicio\GarantiaDatosRequest;
use App\Http\Requests\Servicio\GarantiaEnviarRequest;
use App\Http\Requests\Servicio\GarantiaResolverRequest;
use App\Http\Requests\Servicio\RepuestosUsadosRequest;
use App\Http\Requests\Servicio\PresupuestoBorradorRequest;
use App\Http\Resources\Servicio\OrdenTarjetaResource;
use App\Http\Resources\Servicio\OrdenTrabajoResource;
use App\Models\Compras\Proveedor;
use App\Models\Producto;
use App\Models\Servicio\Falla;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Servicio\Trabajo;
use App\Models\Usuario;
use App\Services\Servicio\EjecucionServicioService;
use App\Services\Servicio\GarantiaProveedorService;
use App\Services\Servicio\OrdenTecnicoConsultaService;
use App\Services\Servicio\OrdenTrabajoService;
use App\Services\Servicio\PresupuestoService;
use App\Services\Servicio\StockServicioService;
use App\Services\Servicio\TecnicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ruta exclusiva del técnico: T1 (tablero) y T2 (orden + diagnóstico).
 * Las rutas usan el sse_id porque una solicitud RECIBIDA todavía no tiene OT.
 *
 * Autorización en dos capas: el middleware exige el permiso
 * servicios.tecnico.ver; acá se verifica además que la orden sea del
 * técnico autenticado (el Administrador ve todas).
 */
class OrdenTecnicoController extends Controller
{
    public function __construct(
        private OrdenTecnicoConsultaService $consulta,
        private OrdenTrabajoService $ordenes,
        private TecnicoService $tecnicos,
        private PresupuestoService $presupuestos,
        private StockServicioService $stock,
        private EjecucionServicioService $ejecucion,
        private GarantiaProveedorService $garantias,
    ) {}

    // GET /api/admin/servicios/tecnico/ordenes?buscar=&tecnico=
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->usuario();
        $esAdmin = OrdenTecnicoConsultaService::esAdmin($usuario);

        $lista = $this->consulta->tablero(
            $usuario,
            $esAdmin && $request->filled('tecnico') ? (int) $request->tecnico : null,
            $request->input('buscar'),
        );

        return response()->json([
            'data' => OrdenTarjetaResource::collection($lista),
            'meta' => [
                'es_admin' => $esAdmin,
                // El combo de técnicos solo lo usa el Administrador.
                'tecnicos' => $esAdmin ? $this->tecnicos->activos() : [],
            ],
        ], 200);
    }

    // GET /api/admin/servicios/tecnico/ordenes/{sseId}
    public function show(int $sseId): JsonResponse
    {
        return response()->json(['data' => new OrdenTrabajoResource($this->cargar($sseId))], 200);
    }

    // GET /api/admin/servicios/tecnico/ordenes/{sseId}/config  — combos del diagnóstico
    public function config(int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);

        $revision = Trabajo::activos()->where('trb_codigo', config('servicio.trabajo_revision'))->first();

        return response()->json([
            'fallas' => Falla::activas()->paraTipo($s->sse_tdiid)
                ->orderBy('fll_descri')->get(['fll_id', 'fll_codigo', 'fll_descri', 'fll_tdiid']),
            'precio_revision' => $revision ? (float) $revision->trb_precio : null,
            // T3: catálogo de mano de obra
            'trabajos' => Trabajo::activos()->with('tipoIva:tiv_id,tiv_tasa')->orderBy('trb_descri')
                ->get(['trb_id', 'trb_codigo', 'trb_descri', 'trb_precio', 'trb_tivid'])
                ->map(fn ($t) => [
                    'trb_id' => $t->trb_id, 'trb_codigo' => $t->trb_codigo, 'trb_descri' => $t->trb_descri,
                    'trb_precio' => (float) $t->trb_precio, 'tasa_iva' => (float) ($t->tipoIva->tiv_tasa ?? 0),
                ]),
            'max_fotos'       => (int) config('servicio.max_fotos_diagnostico'),
            // Garantía de compra: empresas a las que se puede gestionar el reclamo, con su contacto.
            'proveedores' => Proveedor::where('pro_estado', 1)
                ->with(['contactos' => fn ($q) => $q->where('cpr_estado', 1)->orderByDesc('cpr_princi')->orderBy('cpr_nombre')])
                ->orderBy('pro_razon')->get()
                ->map(fn ($p) => [
                    'pro_id'    => $p->pro_id,
                    'razon'     => $p->pro_razon,
                    'telef'     => $p->pro_telef,
                    'email'     => $p->pro_email,
                    'contactos' => $p->contactos->map(fn ($c) => [
                        'nombre' => trim($c->cpr_nombre . ' ' . $c->cpr_apellido),
                        'cargo'  => $c->cpr_cargo,
                        'telef'  => $c->cpr_telef,
                        'email'  => $c->cpr_email,
                    ])->values(),
                ])->values(),
        ], 200);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/iniciar
    public function iniciar(int $sseId): JsonResponse
    {
        $orden = $this->ordenes->iniciar($this->cargar($sseId), $this->usuario());

        return $this->respuesta("Orden {$orden->ose_numero} iniciada", $sseId, 201);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/diagnostico   (multipart/form-data)
    public function diagnostico(DiagnosticoRequest $request, int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        abort_if(!$s->ordenServicio, 422, 'Primero inicie la orden de trabajo.');

        $this->ordenes->guardarDiagnostico($s->ordenServicio, $request->validated(), $request->file('fotos', []), $this->usuario());

        return $this->respuesta('Diagnóstico guardado correctamente', $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/no-reparable
    public function noReparable(CerrarNoReparableRequest $request, int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        abort_if(!$s->ordenServicio, 422, 'La solicitud no tiene orden de trabajo.');

        $this->ordenes->cerrarNoReparable(
            $s->ordenServicio, $request->validated('cobrar_revision'), $request->validated('ose_proced'), $this->usuario()
        );

        return $this->respuesta('Orden cerrada: equipo no reparable. La solicitud quedó lista para devolver.', $sseId);
    }

    // ---------- Garantía de compra: gestión con la empresa responsable ----------

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/garantia/iniciar
    public function iniciarGarantia(int $sseId): JsonResponse
    {
        $this->garantias->iniciar($this->cargar($sseId), $this->usuario());

        return $this->respuesta('Gestión de garantía iniciada', $sseId, 201);
    }

    // PUT /api/admin/servicios/tecnico/ordenes/{sseId}/garantia
    public function guardarGarantia(GarantiaDatosRequest $request, int $sseId): JsonResponse
    {
        $this->garantias->guardar($this->cargar($sseId), $request->validated(), $this->usuario());

        return $this->respuesta('Datos de la garantía guardados', $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/garantia/enviar
    public function enviarGarantia(GarantiaEnviarRequest $request, int $sseId): JsonResponse
    {
        $this->garantias->enviar($this->cargar($sseId), $request->validated(), $this->usuario());

        return $this->respuesta('Producto enviado a la empresa. Se avisó al cliente.', $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/garantia/resolver
    public function resolverGarantia(GarantiaResolverRequest $request, int $sseId): JsonResponse
    {
        $this->garantias->resolver($this->cargar($sseId), $request->validated(), $this->usuario());

        return $this->respuesta('Gestión cerrada: el producto quedó listo para entregar al cliente.', $sseId);
    }

    // GET /api/admin/servicios/tecnico/ordenes/{sseId}/productos?buscar=
    // Buscador de repuestos de T3. El disponible es CONSULTA informativa (regla 1).
    public function productos(Request $request, int $sseId): JsonResponse
    {
        $s       = $this->cargar($sseId);
        $buscar  = trim((string) $request->input('buscar'));
        $deposito = $this->stock->depositoTaller($s);

        // Texto de 1 letra: sin resultados. Texto vacío: se listan los repuestos
        // con stock disponible en el depósito del taller (para poder explorarlos).
        if ($buscar !== '' && mb_strlen($buscar) < 2) {
            return response()->json(['data' => [], 'meta' => ['deposito' => $deposito?->dpo_nombre]], 200);
        }

        // Solo repuestos de taller (prd_repues = 1): los productos de venta no se ofrecen aquí.
        $query = Producto::with('tipoIva:tiv_id,tiv_tasa')->where('prd_estado', 1)->where('prd_repues', 1);

        if ($buscar === '') {
            if (!$deposito) {
                return response()->json(['data' => [], 'meta' => ['deposito' => null]], 200);
            }
            $conStock = \App\Models\Compras\Stock::where('sto_depid', $deposito->dpo_id)
                ->whereRaw('(sto_cantid - sto_reserv) > 0')
                ->pluck('sto_prdid');
            $query->whereIn('prd_id', $conStock);
        } else {
            $like = '%' . mb_strtolower($buscar) . '%';
            // prd_titulo ya no existe: el nombre del producto es prd_descri.
            $query->where(fn ($q) => $q->whereRaw('LOWER(prd_codinv) LIKE ?', [$like])
                ->orWhereRaw('LOWER(prd_descri) LIKE ?', [$like]));
        }

        $productos = $query->orderBy('prd_descri')->limit(15)->get();

        $disponibles = $this->stock->disponibles($productos->pluck('prd_id')->all(), $deposito?->dpo_id);

        return response()->json([
            'data' => $productos->map(fn ($p) => [
                'prd_id'     => $p->prd_id,
                'prd_codinv' => $p->prd_codinv,
                'descri'     => mb_substr((string) $p->prd_descri, 0, 150),
                'precio'     => $p->precioPara(1),
                'tasa_iva'   => (float) ($p->tipoIva->tiv_tasa ?? 0),
                'controla'   => (bool) $p->prd_afestk,
                'disponible' => $disponibles[$p->prd_id] ?? 0.0,
            ]),
            'meta' => ['deposito' => $deposito?->dpo_nombre],
        ], 200);
    }

    // PUT /api/admin/servicios/tecnico/ordenes/{sseId}/presupuesto  — guarda el borrador (T3)
    public function guardarPresupuesto(PresupuestoBorradorRequest $request, int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        abort_if(!$s->ordenServicio, 422, 'La solicitud no tiene orden de trabajo.');

        $this->presupuestos->guardarBorrador($s->ordenServicio, $request->validated(), $this->usuario());

        return $this->respuesta('Presupuesto guardado como borrador', $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/presupuesto/enviar
    public function enviarPresupuesto(int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        abort_if(!$s->ordenServicio, 422, 'La solicitud no tiene orden de trabajo.');

        $p = $this->presupuestos->enviarAValidacion($s->ordenServicio, $this->usuario());

        return $this->respuesta(
            $s->sse_garant ? 'Garantía: presupuesto aprobado sin cargo. Puede iniciar la reparación.' : 'Presupuesto enviado a validación',
            $sseId
        );
    }

    // ===================== Paso 5 - T4 Ejecución =====================

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/reparacion/iniciar  (también "reanudar")
    public function iniciarReparacion(int $sseId): JsonResponse
    {
        $this->ejecucion->iniciarReparacion($this->cargar($sseId), $this->usuario());

        return $this->respuesta('Reparación en curso', $sseId);
    }

    // PUT /api/admin/servicios/tecnico/ordenes/{sseId}/repuestos
    public function guardarRepuestos(RepuestosUsadosRequest $request, int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        $this->ejecucion->guardarRepuestos($s->ordenServicio, $request->validated('repuestos'));

        return $this->respuesta('Cantidades guardadas', $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/finalizar
    public function finalizar(FinalizarOrdenRequest $request, int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        abort_if(!$s->ordenServicio, 422, 'La solicitud no tiene orden de trabajo.');

        $orden = $this->ejecucion->finalizar($s->ordenServicio, $request->validated(), $this->usuario());

        return $this->respuesta("Orden {$orden->ose_numero} finalizada. El equipo quedó listo para entregar.", $sseId);
    }

    // POST /api/admin/servicios/tecnico/ordenes/{sseId}/presupuesto/complementario
    public function abrirComplementario(int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        $p = $this->presupuestos->crearComplementario($s->ordenServicio, $this->usuario());

        return $this->respuesta("Presupuesto complementario {$p->pse_numero} abierto: agregue lo adicional", $sseId, 201);
    }

    // DELETE /api/admin/servicios/tecnico/ordenes/{sseId}/presupuesto/complementario
    public function descartarComplementario(int $sseId): JsonResponse
    {
        $s = $this->cargar($sseId);
        $this->presupuestos->descartarComplementario($s->ordenServicio, $this->usuario());

        return $this->respuesta('Presupuesto complementario descartado', $sseId);
    }

    // GET /api/admin/servicios/tecnico/ordenes/{sseId}/clave
    public function clave(int $sseId): JsonResponse
    {
        $clave = $this->ordenes->revelarClave($this->cargar($sseId), $this->usuario());

        return response()->json(['data' => ['clave' => $clave]], 200);
    }

    // ---------- Auxiliares ----------

    private function usuario(): Usuario
    {
        $u = auth('api')->user();
        $u->loadMissing('rol');

        return $u;
    }

    /** Carga con eager loading completo y verifica que la orden sea del técnico. */
    private function cargar(int $sseId): SolicitudServicio
    {
        $s = SolicitudServicio::with([
            'cliente.persona', 'tipoDispositivo', 'estadoServicio', 'adjuntos',
            'recepcion.accesorios', 'recepcion.tecnico.persona',
            'ordenServicio.diagnostico.fallas.falla',
            'ordenServicio.presupuestoActual.estado', 'ordenServicio.presupuestoActual.detalles',
            'ordenServicio.presupuestos.estado', 'ordenServicio.repuestos.producto',
            // Garantía de compra: producto comprado, su pedido y la gestión con la empresa.
            'detallePedido.producto:prd_id,prd_proid', 'pedido:ped_id,ped_numero', 'garantiaProveedor.proveedor',
        ])->findOrFail($sseId);

        abort_if(!$s->recepcion, 404, 'La solicitud todavía no fue recibida en el taller.');
        abort_unless($this->consulta->puedeVer($s, $this->usuario()), 403, 'Esta orden está asignada a otro técnico.');

        // Precio de lista y disponible por línea (solo para la vista interna).
        if ($p = $s->ordenServicio?->presupuestoActual) {
            $this->stock->anotarReferencias($p);
        }

        return $s;
    }

    private function respuesta(string $mensaje, int $sseId, int $codigo = 200): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data'    => new OrdenTrabajoResource($this->cargar($sseId)),
        ], $codigo);
    }
}
