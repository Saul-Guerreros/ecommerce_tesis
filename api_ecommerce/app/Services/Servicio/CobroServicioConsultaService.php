<?php

namespace App\Services\Servicio;

use App\Http\Resources\Servicio\ServicioPagoResource;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Venta\VentaCab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lecturas del panel para el cobro del servicio: bandeja, config de los
 * modales y la sección "Pagos" del detalle / A5. Solo lee.
 *
 * Los flags (acciones.*, pagos[].puede_*) repiten las reglas de los
 * services para habilitar botones; la regla que manda es la del service.
 */
class CobroServicioConsultaService
{
    /** Relaciones que necesita ServicioPagoResource. */
    public const RELACIONES_PAGO = [
        'metodoPago', 'estadoPago', 'motivoRechazo', 'sucursal:suc_id,suc_nombre',
        'comprobante.usuarioValidador.persona:per_id,per_nombr1,per_apell1',
        'cobro.metodoPago', 'cobro.usuario.persona:per_id,per_nombr1,per_apell1',
        'usuarioVerificador.persona:per_id,per_nombr1,per_apell1',
        'usuarioCobro.persona:per_id,per_nombr1,per_apell1',
    ];

    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private SolicitudServicioService $solicitudes,
        private FacturaServicioConsultaService $facturas,
    ) {}

    /** Bandeja: por defecto lo que espera verificación o cobro. */
    public function query(array $f): Builder
    {
        return ServicioPago::query()
            ->with([
                'metodoPago', 'estadoPago', 'comprobante:sco_id,sco_spaid,sco_verifi',
                'solicitud:sse_id,sse_numero,sse_cliid,sse_sucid,sse_etsid,sse_total',
                'solicitud.estadoServicio', 'solicitud.sucursal:suc_id,suc_nombre',
                'solicitud.cliente.persona:per_id,per_nombr1,per_apell1,per_cedula',
            ])
            ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', $f['estado'] ?? EstadoPago::PAGO_VERIFICAR))
            ->when($f['canal'] ?? null, fn (Builder $q, $v) => $q->where('spa_canal', $v))
            ->when($f['mpa_id'] ?? null, fn (Builder $q, $v) => $q->where('spa_mpaid', $v))
            ->when($f['suc_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('solicitud', fn ($s) => $s->where('sse_sucid', $v)))
            ->when($f['desde'] ?? null, fn (Builder $q, $v) => $q->whereDate('spa_creado', '>=', $v))
            ->when($f['hasta'] ?? null, fn (Builder $q, $v) => $q->whereDate('spa_creado', '<=', $v))
            ->when($f['buscar'] ?? null, function (Builder $q, $buscar) {
                $like = '%' . mb_strtolower($buscar) . '%';
                $q->whereHas('solicitud', fn ($s) => $s->where(fn ($w) => $w
                    ->whereRaw('LOWER(sse_numero) LIKE ?', [$like])
                    ->orWhereHas('cliente.persona', fn ($p) => $p->where(fn ($pp) => $pp
                        ->whereRaw("LOWER(per_nombr1 || ' ' || per_apell1) LIKE ?", [$like])
                        ->orWhere('per_cedula', 'like', "%{$buscar}%")))));
            })
            ->orderBy('spa_creado')
            ->orderBy('spa_id');
    }

    public function config(Usuario $usuario): array
    {
        return [
            'estados'    => EstadoPago::whereIn('epa_codigo', [EstadoPago::PAGO_VERIFICAR, EstadoPago::CONFIRMADO, EstadoPago::RECHAZADO])
                ->get(['epa_id', 'epa_codigo', 'epa_descri']),
            'metodos'    => MetodoPago::activos()->orderBy('mpa_id')->get(['mpa_id', 'mpa_codigo', 'mpa_descri', 'mpa_requcom']),
            'motivos'    => MotivoRechazoPago::activos()->get(['mrp_id', 'mrp_codigo', 'mrp_descri', 'mrp_requdet']),
            'sucursales' => Sucursal::where('suc_estado', 1)->orderBy('suc_nombre')->get(['suc_id', 'suc_nombre']),
            'permisos'   => $this->permisos($usuario),
        ];
    }

    /** Sección "Pagos" del detalle de la solicitud y del modal A5. */
    public function detalle(SolicitudServicio $s, Usuario $usuario): array
    {
        $s->loadMissing([
            'estadoServicio', 'estadoPago', 'ordenServicio',
            'pagos' => fn ($q) => $q->with(self::RELACIONES_PAGO),
        ]);

        $permisos   = $this->permisos($usuario);
        $enSucursal = $this->enSucursal($s, $usuario);
        $resumen    = $this->estadoPago->resumen($s);

        $anticipo = $this->facturas->resumenPanel($s, VentaCab::TIPO_ANTICIPO);
        $final    = $this->facturas->resumenPanel($s, VentaCab::TIPO_VENTA);

        // Anular exige además el permiso propio (servicios.facturas.anular).
        foreach ([&$anticipo, &$final] as &$f) {
            if ($f !== null) {
                $f['acciones']['anular'] = $f['acciones']['anular'] && $permisos['anular_factura'];
            }
        }
        unset($f);

        $contexto = [
            'factura_anticipo'   => $anticipo !== null,
            'factura_final'      => $final !== null,
            'documento_anticipo' => $this->conDocumento($anticipo),
            'documento_final'    => $this->conDocumento($final),
            'verificar'          => $permisos['verificar'],
            'gestiona'           => $permisos['cobrar'] || $permisos['verificar'],
            'cajero'             => $permisos['cobrar'] && $enSucursal,
            'anular_cobro'       => $permisos['cobrar'] && $s->codigoServicio() !== EstadoServicio::ENTREGADA,
        ];

        return [
            'sse_id'      => $s->sse_id,
            'sse_numero'  => $s->sse_numero,
            'estado_pago' => $s->estadoPago ? [
                'codigo'      => $s->estadoPago->epa_codigo,
                'descripcion' => $s->estadoPago->epa_descri,
            ] : null,
            'resumen'     => $resumen,
            'pagos'       => $s->pagos
                ->map(fn ($p) => (new ServicioPagoResource($p, $contexto))->resolve())
                ->values()->all(),
            'facturas'    => ['anticipo' => $anticipo, 'final' => $final],
            'devolucion'  => $this->devolucion($s, $permisos, $enSucursal),
            'acciones'    => $this->acciones($s, $permisos, $enSucursal, $resumen, $anticipo, $final),
            'anuladas'    => $this->facturas->anuladas($s),
        ];
    }

    /** Permisos efectivos (el Administrador pasa siempre, como en CheckPermission). */
    public function permisos(Usuario $usuario): array
    {
        $admin = $usuario->rol?->rol_descri === 'Administrador';

        return [
            'cobrar'    => $admin || $usuario->tienePermiso('servicios.cobros.confirmar'),
            'verificar' => $admin || $usuario->tienePermiso('pedidos.pago.verificar'),
            'nota_credito' => $admin || $usuario->tienePermiso('notas-credito-venta.ver'),
            'anular_factura' => $admin || $usuario->tienePermiso('servicios.facturas.anular'),
        ];
    }

    // ---------- Flags ----------

    private function acciones(SolicitudServicio $s, array $permisos, bool $enSucursal, array $resumen, ?array $anticipo, ?array $final): array
    {
        $codigo      = $s->codigoServicio();
        $gestiona    = $permisos['cobrar'] || $permisos['verificar'];
        $etapaSena   = in_array($codigo, EstadoPagoServicioService::ESTADOS_ANTICIPO, true);
        $cajero      = $permisos['cobrar'] && $enSucursal;
        $cobrable    = !$s->sse_garant && (float) $s->sse_total > 0;

        return [
            'declarar_anticipo' => $cajero && $etapaSena && $this->estadoPago->admitePagos($s)
                && $anticipo === null && $resumen['anticipo'] === 0,
            'tope_anticipo'     => $etapaSena ? $this->estadoPago->topeAnticipo($s) : null,

            // Anticipo web declarado y todavía sin factura.
            'generar_anticipo'  => $gestiona && $etapaSena && $anticipo === null && $resumen['anticipo'] > 0,

            'generar_factura'   => $gestiona && $codigo === EstadoServicio::LISTA && $cobrable && $final === null,
            'saldo_por_declarar'=> $resumen['saldo'],
            'declarar_medios'   => $cajero,

            'cobrar_entregar'   => $cajero && $codigo === EstadoServicio::LISTA,
            'verificar'         => $permisos['verificar'],
            'cobrar'            => $cajero,
        ];
    }

    /** Hay documento para respaldar un cobro (sin facturación electrónica: alcanza con la factura). */
    private function conDocumento(?array $factura): bool
    {
        if ($factura === null) {
            return false;
        }

        return !config('facturacion.activa') || (bool) ($factura['fe']['documento'] ?? false);
    }

    private function enSucursal(SolicitudServicio $s, Usuario $usuario): bool
    {
        return $s->sse_sucid !== null
            && $this->solicitudes->sucursalesMostrador($usuario)->contains('suc_id', $s->sse_sucid);
    }

    /**
     * Devoluciones del servicio: saldo del anticipo, liberación desde la
     * final y las NC de AMBAS facturas con sus devoluciones.
     * null = no hay anticipo ni NC sobre la final.
     */
    private function devolucion(SolicitudServicio $s, array $permisos, bool $enSucursal): ?array
    {
        $relaciones = [
            'fe',
            'notasCredito.fe',
            'notasCredito.devoluciones.metodoPago',
            'notasCredito.devoluciones.usuario.persona:per_id,per_nombr1,per_apell1',
        ];

        $anticipo = $s->facturaAnticipo()->with($relaciones)->first();
        $final    = $s->factura()->with($relaciones)->first();

        if (!$anticipo && (!$final || $final->notasCredito->isEmpty())) {
            return null;
        }

        $electronica = (bool) config('facturacion.activa');
        $disponible  = $anticipo?->disponibleAnticipo() ?? 0.0;
        $estadosFe   = [1 => 'PENDIENTE', 2 => 'APROBADA', 3 => 'RECHAZADA'];
        $activa      = \App\Models\Venta\DevolucionVenta::ESTADO_ACTIVA;

        $aplica     = $final !== null && $final->ven_antvenid !== null;
        $acreditada = $final !== null && $final->totalAcreditado() + 0.01 >= (float) $final->ven_total;

        $mapear = function ($n, string $origen) use ($permisos, $electronica, $estadosFe, $activa) {
            $porDevolver = $n->porDevolver();

            return [
                'ncv_id'         => $n->ncv_id,
                'factura'        => $origen,   // ANTICIPO | FINAL
                'numero'         => $n->ncv_nrodoc,
                'fecha'          => $n->ncv_fecha ? \Illuminate\Support\Carbon::parse($n->ncv_fecha)->format('d/m/Y') : null,
                'total'          => (float) $n->ncv_total,
                'anulada'        => $n->anulada(),
                'fe'             => $n->fe ? ['estado' => $estadosFe[$n->fe->nfe_estado] ?? 'PENDIENTE', 'cdc' => $n->fe->nfe_cdc] : null,
                'devuelto'       => $n->totalDevuelto(),
                'por_devolver'   => $porDevolver,
                'puede_devolver' => $permisos['cobrar'] && $porDevolver > 0.01
                    && (!$electronica || (bool) $n->fe?->estaEnviado()),
                'devoluciones'   => $n->devoluciones->map(fn ($d) => [
                    'dev_id'           => $d->dev_id,
                    'monto'            => (float) $d->dev_monto,
                    'metodo'           => $d->metodoPago?->mpa_descri,
                    'nro_transaccion'  => $d->dev_nrotrx,
                    'banco'            => $d->dev_banco,
                    'fecha'            => $d->dev_fecha?->format('d/m/Y'),
                    'observacion'      => $d->dev_observ,
                    'por'              => trim(($d->usuario?->persona?->per_nombr1 ?? '') . ' '
                                        . ($d->usuario?->persona?->per_apell1 ?? '')) ?: null,
                    'anulada'          => $d->dev_estado !== $activa,
                    'motivo_anulacion' => $d->dev_motanu,
                    'puede_anular'     => $permisos['cobrar'] && $d->dev_estado === $activa,
                ])->values()->all(),
            ];
        };

        $notas = collect()
            ->concat(($anticipo?->notasCredito ?? collect())->map(fn ($n) => $mapear($n, 'ANTICIPO')))
            ->concat(($final?->notasCredito ?? collect())->map(fn ($n) => $mapear($n, 'FINAL')))
            ->values()->all();

        return [
            'disponible'      => $disponible,
            'por_devolver'    => round(($anticipo?->dineroPorDevolver() ?? 0) + ($final?->dineroPorDevolver() ?? 0), 2),
            'puede_acreditar' => $anticipo !== null && $permisos['nota_credito'] && $disponible > 0.01
                && (!$electronica || (bool) $anticipo->fe?->estaEnviado()),

            'liberacion'      => $aplica ? [
                'aplicado'   => (float) $final->ven_antapl,
                'liberado'   => (float) $final->ven_antlib,
                'fecha'      => $final->ven_fechlib?->format('d/m/Y H:i'),
                'automatica' => $final->ven_ncvlib !== null,
            ] : null,
            'puede_liberar'   => $aplica && $permisos['nota_credito'] && !$final->anticipoLiberado()
                && ((float) $final->ven_total <= 0.01 || $acreditada),

            'notas'           => $notas,
        ];
    }
}