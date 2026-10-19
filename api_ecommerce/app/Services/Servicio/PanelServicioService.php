<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Support\Facades\DB;

/**
 * A3 / RF-040 - Indicadores del panel general. Consultas agregadas (COUNT /
 * GROUP BY) en lugar de traer filas: el panel se refresca seguido.
 */
class PanelServicioService
{
    public function __construct(private TecnicoService $tecnicos) {}

    public function resumen(): array
    {
        // Solicitudes por estado del ciclo técnico (solo abiertas)
        $porEstado = DB::table('solicitud_servicio as s')
            ->join('estado_servicio as e', 'e.ets_id', '=', 's.sse_etsid')
            ->whereNotIn('e.ets_codigo', ['ENTREGADA', 'CANCELADA'])
            ->groupBy('e.ets_codigo', 'e.ets_descri', 'e.ets_orden')
            ->orderBy('e.ets_orden')
            ->get(['e.ets_codigo as codigo', 'e.ets_descri as descri', DB::raw('COUNT(*) as total')]);

        $hoy    = today();
        $limite = $hoy->copy()->addDays((int) config('servicio.alerta_vencimiento_dias'));

        $enviados = PresupuestoServicio::whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::ENVIADO));

        // Carga por técnico: órdenes abiertas asignadas
        $carga = DB::table('recepcion_servicio as r')
            ->join('solicitud_servicio as s', 's.sse_id', '=', 'r.rse_sseid')
            ->join('estado_servicio as e', 'e.ets_id', '=', 's.sse_etsid')
            ->whereIn('e.ets_codigo', ['RECIBIDA', 'EN_DIAGNOSTICO', 'PRESUPUESTADA', 'APROBADA', 'EN_REPARACION'])
            ->groupBy('r.rse_tecid')
            ->select('r.rse_tecid', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'rse_tecid');

        return [
            'por_estado' => $porEstado,
            'tarjetas'   => [
                'pendientes_recepcion' => $this->contarEstado('SOLICITADA'),
                'en_taller'            => $porEstado->whereIn('codigo', ['RECIBIDA', 'EN_DIAGNOSTICO', 'APROBADA', 'EN_REPARACION'])->sum('total'),
                'presupuestos_validar' => PresupuestoServicio::whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::EN_REVISION))->count(),
                'esperando_cliente'    => (clone $enviados)->count(),
                'por_vencer'           => (clone $enviados)->whereBetween('pse_fecven', [$hoy, $limite])->count(),
                'listos_entregar'      => $this->contarEstado('LISTA'),
                'cobros_pendientes'    => SolicitudServicio::whereHas('estadoServicio', fn ($q) => $q->where('ets_codigo', 'LISTA'))
                    ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::PENDIENTE))->count(),
            ],
            'tecnicos' => $this->tecnicos->activos()->map(fn ($t) => $t + ['abiertas' => (int) ($carga[$t['usu_id']] ?? 0)])->values(),
        ];
    }

    private function contarEstado(string $codigo): int
    {
        return SolicitudServicio::whereHas('estadoServicio', fn ($q) => $q->where('ets_codigo', $codigo))->count();
    }
}
