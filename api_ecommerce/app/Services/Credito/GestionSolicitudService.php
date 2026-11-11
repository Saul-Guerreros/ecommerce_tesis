<?php

namespace App\Services\Credito;

use App\Models\Carrito\ConfiguracionCuota;
use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoCuota;
use App\Models\Credito\SolicitudCreditoHistorial;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestion de la solicitud de credito desde el PANEL ADMIN.
 *
 * Modulo transaccional segun el estandar: el controlador no persiste
 * nada, delega acá. Cada operacion que toca mas de una tabla
 * (detalle + cabecera + cronograma + historial) corre dentro de
 * DB::transaction().
 *
 * Separado de SolicitudService a proposito: aquel resuelve la
 * GENERACION desde el carrito (lado cliente) y este la EVALUACION
 * (lado admin). Son dos ciclos de vida distintos sobre la misma
 * entidad y mezclarlos daria una clase que nadie puede leer.
 */
class GestionSolicitudService
{
    /** Relaciones del listado: lo justo para pintar la fila. */
    public const CON_LISTA = ['estado'];

    /** Relaciones del detalle. Sin esto, las 6 pestañas son un N+1. */
    public const CON_DETALLE = [
        'estado',
        'ciudad.departamento',
        'profesion',
        'tipoProfesion',
        'detalles.producto.publicacionWeb',
        'referencias',
        'cuotas',
        'historial.estadoAnterior',
        'historial.estadoNuevo',
        'historial.usuario.persona',
        'usuarioDecision.persona',
        'cliente.persona',
    ];

    public function __construct(
        private StockCreditoService $stock,
    ) {}

    // ====================================================================
    //  Consulta
    // ====================================================================

    /**
     * Bandeja de solicitudes. Un solo criterio de busqueda a la vez
     * (campo + texto), igual que el combo del diseño.
     *
     * La busqueda va contra los campos SNAPSHOT de la solicitud
     * (scr_cedula, scr_nombre, scr_apelli) y no contra persona: el
     * administrador busca por lo que el cliente DECLARO, que es lo que
     * esta evaluando. Si el cliente despues cambia su perfil, la
     * solicitud se sigue encontrando por los datos de la solicitud.
     */
    public function query(array $filtros): Builder
    {
        $query = SolicitudCredito::query()->with(self::CON_LISTA);

        if (! empty($filtros['estado'])) {
            $query->where('scr_escid', (int) $filtros['estado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('scr_creado', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('scr_creado', '<=', $filtros['hasta']);
        }

        $texto = trim((string) ($filtros['q'] ?? ''));

        if ($texto !== '') {
            $campo = $filtros['campo'] ?? 'numero';

            match ($campo) {
                'ci' => $query->where('scr_cedula', 'ILIKE', "%{$texto}%"),

                'cliente' => $query->whereRaw(
                    "(scr_nombre || ' ' || scr_apelli) ILIKE ?",
                    ["%{$texto}%"]
                ),

                default => $query->where('scr_numero', 'ILIKE', "%{$texto}%"),
            };
        }

        return $query->orderByDesc('scr_creado');
    }

    /** Contador global de la alerta: NO depende de los filtros aplicados. */
    public function pendientes(): int
    {
        return SolicitudCredito::whereHas(
            'estado',
            fn ($q) => $q->where('esc_codigo', EstadoSolicitud::PENDIENTE)
        )->count();
    }

    public function detalle(int $id): SolicitudCredito
    {
        return SolicitudCredito::with(self::CON_DETALLE)->findOrFail($id);
    }

    /**
     * Planes de financiacion vigentes para el producto de una linea.
     * Alimenta el combo del modal [Cambiar].
     *
     * Se calcula contra scd_preuni (el precio CONGELADO de la solicitud)
     * y no contra el precio actual del catalogo: cambiar el plazo no
     * puede cambiar el precio que el cliente ya vio.
     */
    public function planesDisponibles(SolicitudCredito $solicitud, int $detalleId): array
    {
        $detalle = $solicitud->detalles()->where('scd_id', $detalleId)->firstOrFail();
        $precio  = (float) $detalle->scd_preuni;

        return ConfiguracionCuota::where('cfc_prdid', $detalle->scd_prdid)
            ->activas()
            ->orderBy('cfc_cantidad')
            ->get()
            ->map(fn (ConfiguracionCuota $cfg) => [
                'cantidad'     => (int) $cfg->cfc_cantidad,
                'recargo_pct'  => (float) $cfg->cfc_recargo_pct,
                'monto_cuota'  => $cfg->montoCuota($precio),
                'total_linea'  => $cfg->precioFinanciado($precio) * (float) $detalle->scd_cantid,
                'actual'       => (int) $cfg->cfc_cantidad === (int) $detalle->scd_numcuo,
            ])
            ->values()
            ->all();
    }

    // ====================================================================
    //  Transiciones de estado
    // ====================================================================

    /** [TOMAR EN EVALUACION]: PENDIENTE -> EN_REVISION. */
    public function tomarEnEvaluacion(SolicitudCredito $solicitud, Usuario $usuario): SolicitudCredito
    {
        if (! $solicitud->puedeTomarse()) {
            abort(409, 'Solo una solicitud recibida puede tomarse en evaluación.');
        }

        return DB::transaction(function () use ($solicitud, $usuario) {
            $anterior = (int) $solicitud->scr_escid;
            $nuevo    = EstadoSolicitud::porCodigo(EstadoSolicitud::EN_REVISION);

            $solicitud->update(['scr_escid' => $nuevo->esc_id]);

            $this->registrarHistorial(
                $solicitud, $anterior, $nuevo->esc_id, $usuario,
                'Tomada en evaluación.'
            );

            return $this->detalle($solicitud->scr_id);
        });
    }

    /**
     * [GUARDAR DECISION]: APROBAR o RECHAZAR.
     *
     * Al aprobar se congela el cronograma en solicitud_credito_cuota: a
     * partir de ahi es el compromiso que el cliente acepta en la etapa
     * D, y no se recalcula aunque cambie la configuracion del producto.
     */
    public function decidir(SolicitudCredito $solicitud, Usuario $usuario, array $datos): SolicitudCredito
    {
        $this->exigirAbierta($solicitud);

        $aprobar = $datos['decision'] === 'APROBAR';

        if ($aprobar && $solicitud->detalles()->count() === 0) {
            throw ValidationException::withMessages([
                'decision' => 'No se puede aprobar una solicitud sin productos.',
            ]);
        }

        return DB::transaction(function () use ($solicitud, $usuario, $datos, $aprobar) {
            $anterior = (int) $solicitud->scr_escid;

            $nuevo = EstadoSolicitud::porCodigo(
                $aprobar ? EstadoSolicitud::APROBADA : EstadoSolicitud::RECHAZADA
            );

            if ($aprobar) {
                // Si el administrador no fijo la fecha en la pestaña
                // Compra, se asume un mes desde hoy: el cliente necesita
                // ver un cronograma con fechas reales para aceptarlo.
                $solicitud->scr_fecpri = $solicitud->scr_fecpri
                    ?? Carbon::today()->addMonthNoOverflow();

                $solicitud->scr_vighas = Carbon::today()->addDays((int) $datos['vigencia_dias']);
                $solicitud->scr_motrec = null;
                $solicitud->scr_detrec = null;
            } else {
                $solicitud->scr_motrec = $datos['motivo'];
                $solicitud->scr_detrec = $datos['detalle'] ?? null;
                $solicitud->scr_vighas = null;
                $solicitud->scr_fecpri = null;
            }

            if (array_key_exists('observaciones', $datos)) {
                $solicitud->scr_observ = $datos['observaciones'];
            }

            $solicitud->scr_escid  = $nuevo->esc_id;
            $solicitud->scr_fecdec = now();
            $solicitud->scr_usudec = $usuario->usu_id;
            $solicitud->save();

            if ($aprobar) {
                $solicitud->load('detalles');
                $this->persistirCronograma($solicitud);
            } else {
                // Un cronograma de una solicitud rechazada solo confunde.
                $solicitud->cuotas()->delete();

                // Devolver el stock apartado: la tienda ya no se compromete
                // con esta solicitud y la mercaderia vuelve a la vitrina.
                $this->stock->liberar($solicitud);
            }

            $this->registrarHistorial(
                $solicitud, $anterior, $nuevo->esc_id, $usuario,
                $aprobar
                    ? 'Aprobada. Oferta vigente hasta ' . $solicitud->scr_vighas->format('d/m/Y') . '.'
                    : 'Rechazada: ' . $solicitud->etiquetaMotivoRechazo() . '.'
            );

            return $this->detalle($solicitud->scr_id);
        });
    }

    // ====================================================================
    //  Edicion de la compra (pestaña Compra y plan)
    // ====================================================================

    /** Observaciones internas sin resolver todavia la solicitud. */
    public function guardarObservaciones(SolicitudCredito $solicitud, ?string $observaciones): SolicitudCredito
    {
        $this->exigirAbierta($solicitud);

        $solicitud->update(['scr_observ' => $observaciones]);

        return $this->detalle($solicitud->scr_id);
    }

    /**
     * [Cambiar]: el administrador baja (o sube) el plan de una linea.
     *
     * Reutiliza ConfiguracionCuota::precioFinanciado() y montoCuota(),
     * los mismos metodos que usa CarritoController al armar el carrito.
     * Si mañana cambia la formula de financiacion, cambia en un solo
     * lugar y las dos pantallas siguen coincidiendo.
     */
    public function cambiarPlan(SolicitudCredito $solicitud, int $detalleId, int $cuotas): SolicitudCredito
    {
        $this->exigirAbierta($solicitud);

        return DB::transaction(function () use ($solicitud, $detalleId, $cuotas) {
            $detalle = $solicitud->detalles()->where('scd_id', $detalleId)->firstOrFail();

            $config = ConfiguracionCuota::where('cfc_prdid', $detalle->scd_prdid)
                ->where('cfc_cantidad', $cuotas)
                ->where('cfc_estado', true)
                ->first();

            if (! $config) {
                throw ValidationException::withMessages([
                    'cuotas' => 'Ese plan de cuotas ya no está disponible para este producto.',
                ]);
            }

            $precioUnitario = (float) $detalle->scd_preuni;
            $cantidad       = (float) $detalle->scd_cantid;
            $subtotal       = $config->precioFinanciado($precioUnitario) * $cantidad;

            $producto = $detalle->producto()->with('tipoIva')->first();

            $detalle->update([
                'scd_numcuo' => (int) $config->cfc_cantidad,
                'scd_recpct' => (float) $config->cfc_recargo_pct,
                'scd_moncuo' => $config->montoCuota($precioUnitario),
                'scd_subtot' => $subtotal,
                'scd_iva'    => $producto ? round($producto->ivaIncluidoEn($subtotal)) : 0,
            ]);

            $solicitud->load('detalles');
            $this->recalcularTotales($solicitud);
            $this->persistirCronograma($solicitud);

            return $this->detalle($solicitud->scr_id);
        });
    }

    /** [Recalcular]: fija la fecha real de la primera cuota. */
    public function fijarCronograma(SolicitudCredito $solicitud, string $fechaPrimera): SolicitudCredito
    {
        $this->exigirAbierta($solicitud);

        return DB::transaction(function () use ($solicitud, $fechaPrimera) {
            $solicitud->update(['scr_fecpri' => $fechaPrimera]);

            $solicitud->load('detalles');
            $this->persistirCronograma($solicitud);

            return $this->detalle($solicitud->scr_id);
        });
    }

    // ====================================================================
    //  Auxiliares privados
    // ====================================================================

    /**
     * Recalcula la cabecera desde el detalle. Mismos campos y mismo
     * criterio que SolicitudService::resumenCredito(), pero leyendo el
     * detalle congelado en vez del carrito (el carrito ya no existe).
     */
    private function recalcularTotales(SolicitudCredito $solicitud): void
    {
        $detalles = $solicitud->detalles;

        $contado = $detalles->sum(fn ($d) => (float) $d->scd_preuni * (float) $d->scd_cantid);
        $total   = $detalles->sum(fn ($d) => (float) $d->scd_subtot);
        $iva     = $detalles->sum(fn ($d) => (float) $d->scd_iva);
        $cuota   = $detalles->sum(fn ($d) => (float) $d->scd_moncuo * (float) $d->scd_cantid);

        $solicitud->update([
            'scr_toncon' => round($contado),
            'scr_recfin' => round($total - $contado),
            'scr_total'  => round($total),
            'scr_impues' => round($iva),
            'scr_plazo'  => (int) $detalles->max('scd_numcuo'),
            'scr_cuomen' => round($cuota),
        ]);
    }

    /**
     * Reescribe solicitud_credito_cuota desde el calculo del modelo.
     *
     * delete + insert y no un diff fila por fila: el cronograma es un
     * bloque indivisible. Un cronograma a medias actualizado es peor que
     * no tener cronograma.
     *
     * Sin fecha de primera cuota no se persiste nada: la pantalla sigue
     * mostrando la estimacion sin vencimientos.
     */
    private function persistirCronograma(SolicitudCredito $solicitud): void
    {
        $solicitud->cuotas()->delete();

        if (! $solicitud->scr_fecpri) {
            return;
        }

        foreach ($solicitud->cronogramaCalculado() as $fila) {
            SolicitudCreditoCuota::create([
                'scc_scrid'  => $solicitud->scr_id,
                'scc_numero' => $fila['numero'],
                'scc_fecven' => $fila['vencimiento'],
                'scc_import' => $fila['importe'],
                'scc_compos' => $fila['composicion'],
            ]);
        }
    }

    private function registrarHistorial(
        SolicitudCredito $solicitud,
        ?int $estadoAnterior,
        int $estadoNuevo,
        ?Usuario $usuario,
        ?string $observacion = null,
    ): void {
        SolicitudCreditoHistorial::create([
            'sch_scrid'  => $solicitud->scr_id,
            'sch_escant' => $estadoAnterior,
            'sch_escnue' => $estadoNuevo,
            'sch_usuid'  => $usuario?->usu_id,
            'sch_observ' => $observacion,
        ]);
    }

    /**
     * Seguridad real en el backend: los botones deshabilitados de
     * Angular son experiencia de usuario, no control de acceso. Un POST
     * armado a mano contra una solicitud ya resuelta se corta acá.
     */
    private function exigirAbierta(SolicitudCredito $solicitud): void
    {
        $solicitud->loadMissing('estado');

        if (! $solicitud->estaAbierta()) {
            abort(409, 'La solicitud ya fue resuelta y no admite modificaciones.');
        }
    }
    
}