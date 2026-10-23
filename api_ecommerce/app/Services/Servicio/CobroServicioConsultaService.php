<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lecturas del panel para el cobro del servicio: bandeja "Cobros de
 * servicios", config de los modales y la sección "Pagos" del detalle / A5.
 * Solo lee: las escrituras viven en los services de pago.
 */
class CobroServicioConsultaService
{
    /** Relaciones que necesita ServicioPagoResource. */
    public const RELACIONES_PAGO = [
        'metodoPago', 'estadoPago', 'comprobante', 'motivoRechazo', 'sucursal:suc_id,suc_nombre',
        'usuarioVerificador.persona:per_id,per_nombr1,per_apell1',
        'usuarioCobro.persona:per_id,per_nombr1,per_apell1',
    ];

    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private SolicitudServicioService $solicitudes,
    ) {}

    /** Bandeja: por defecto lo que espera verificación. */
    public function query(array $f): Builder
    {
        return ServicioPago::query()
            ->with([
                'metodoPago', 'estadoPago', 'comprobante:sco_id,sco_spaid',
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
            // Lo más viejo primero: es lo que más tiempo lleva esperando.
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
        $s->loadMissing(['estadoServicio', 'estadoPago', 'pagos' => fn ($q) => $q->with(self::RELACIONES_PAGO)]);
        $permisos = $this->permisos($usuario);

        return [
            'sse_id'      => $s->sse_id,
            'sse_numero'  => $s->sse_numero,
            'estado_pago' => $s->estadoPago ? [
                'codigo'      => $s->estadoPago->epa_codigo,
                'descripcion' => $s->estadoPago->epa_descri,
            ] : null,
            'resumen'     => $this->estadoPago->resumen($s),
            'pagos'       => $s->pagos,
            // Lo decide el backend (incluye la regla de sucursal): el front no razona permisos.
            'acciones'    => [
                'cobrar'    => $permisos['cobrar'] && $this->estadoPago->admitePagos($s) && $this->enSucursal($s, $usuario),
                'verificar' => $permisos['verificar'],
            ],
        ];
    }

    /** Permisos efectivos (el Administrador pasa siempre, como en CheckPermission). */
    public function permisos(Usuario $usuario): array
    {
        $admin = $usuario->rol?->rol_descri === 'Administrador';

        return [
            'cobrar'    => $admin || $usuario->tienePermiso('servicios.cobros.confirmar'),
            'verificar' => $admin || $usuario->tienePermiso('pedidos.pago.verificar'),
        ];
    }

    private function enSucursal(SolicitudServicio $s, Usuario $usuario): bool
    {
        return $s->sse_sucid !== null
            && $this->solicitudes->sucursalesMostrador($usuario)->contains('suc_id', $s->sse_sucid);
    }
}
