<?php

namespace App\Services\Servicio;

use App\Models\Servicio\SolicitudServicio;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta de la bandeja del admin (A1). Separada del controlador para
 * reutilizarla en el panel general (A3) y en exportaciones.
 */
class ServicioConsultaService
{
    public function query(array $f): Builder
    {
        return SolicitudServicio::query()
            ->with([
                'cliente.persona:per_id,per_nombr1,per_nombr2,per_apell1,per_apell2,per_cedula',
                'tipoDispositivo', 'estadoServicio', 'estadoIngreso', 'tipoEntrega',
                'recepcion.tecnico.persona:per_id,per_nombr1,per_apell1',
                'sucursal:suc_id,suc_nombre',
            ])
            ->when($f['buscar'] ?? null, function (Builder $q, $buscar) {
                $like = '%' . mb_strtolower($buscar) . '%';
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(sse_numero) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sse_marca) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sse_modelo) LIKE ?', [$like])
                    ->orWhereHas('cliente.persona', fn ($p) => $p
                        ->whereRaw("LOWER(per_nombr1 || ' ' || per_apell1) LIKE ?", [$like])
                        ->orWhere('per_cedula', 'like', "%{$buscar}%")));
            })
            // Bandeja A1: solo lo que todavía no está en el taller.
            ->when(!empty($f['solo_pendientes']), fn (Builder $q) => $q
                ->whereHas('estadoServicio', fn ($e) => $e->where('ets_codigo', 'SOLICITADA')))
            // Panel general A3: todo lo que sigue abierto (ni entregado ni cancelado).
            ->when(!empty($f['solo_activas']), fn (Builder $q) => $q
                ->whereHas('estadoServicio', fn ($e) => $e->whereNotIn('ets_codigo', ['ENTREGADA', 'CANCELADA'])))
            ->when($f['ets_codigo'] ?? null, fn (Builder $q, $v) => $q
                ->whereHas('estadoServicio', fn ($e) => $e->where('ets_codigo', $v)))
            ->when($f['tec_id'] ?? null, fn (Builder $q, $v) => $q
                ->whereHas('recepcion', fn ($r) => $r->where('rse_tecid', $v)))
            ->when($f['ets_id'] ?? null, fn (Builder $q, $v) => $q->where('sse_etsid', $v))
            ->when($f['elg_id'] ?? null, fn (Builder $q, $v) => $q->where('sse_elging', $v))
            ->when($f['ten_id'] ?? null, fn (Builder $q, $v) => $q->where('sse_tenid', $v))
            // Filtros opcionales por sucursal y canal (decisión B.6: no restringen lo que ve cada usuario).
            ->when($f['suc_id'] ?? null, fn (Builder $q, $v) => $q->where('sse_sucid', $v))
            ->when($f['canal'] ?? null, fn (Builder $q, $v) => $q->where('sse_canal', $v))
            ->when(isset($f['garantia']) && $f['garantia'] !== '', fn (Builder $q) => $q->where('sse_garant', (bool) $f['garantia']))
            ->when($f['desde'] ?? null, fn (Builder $q, $v) => $q->whereDate('sse_creado', '>=', $v))
            ->when($f['hasta'] ?? null, fn (Builder $q, $v) => $q->whereDate('sse_creado', '<=', $v))
            // Prioridad por orden de llegada (decisión de alcance): la más antigua primero.
            ->orderBy('sse_id');
    }
}
