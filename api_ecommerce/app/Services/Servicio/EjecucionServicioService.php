<?php

namespace App\Services\Servicio;

use App\Models\Compras\MovimientoStock;
use App\Models\Compras\Stock;
use App\Models\Producto;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\OrdenServicio;
use App\Models\Servicio\OrdenServicioRepuesto;
use App\Models\Servicio\PresupuestoDetalle;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use App\Services\Servicio\ServicioTotalesService as Totales;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FASE 5 - Reparación y cierre de la OT (T4).
 *
 * Regla 1: el stock se descuenta SOLO acá, al finalizar.
 * Regla 2: lo reservado al aprobar se libera en el mismo cierre.
 * Regla 3: la salida va por movimiento_stock ('S', mst_osrid), nunca por ajuste_inventario.
 * Regla 4: lo consumido (orden_servicio_repuesto) puede ser MENOR que lo
 *          presupuestado; mayor no (exige complementario).
 */
class EjecucionServicioService
{
    public function __construct(
        private ServicioEstadoService $estados,
        private ServicioTotalesService $totales,
        private StockServicioService $stock,
    ) {}

    // ---------------------------------------------------------------------
    // Inicio / reanudación
    // ---------------------------------------------------------------------

    /** APROBADA -> EN_REPARACION. Se usa al empezar y al reanudar tras un complementario. */
    public function iniciarReparacion(SolicitudServicio $s, Usuario $usuario): void
    {
        $orden = $s->ordenServicio;

        if ($s->codigoServicio() !== EstadoServicio::APROBADA || !$orden || $orden->estaCerrada()) {
            throw ValidationException::withMessages(['estado' => 'La orden no está aprobada para reparar.']);
        }

        DB::transaction(function () use ($s, $orden, $usuario) {
            $reanuda = $orden->repuestos()->exists() || $orden->presupuestos()->count() > 1;
            $this->sincronizarRepuestos($orden);

            $this->estados->cambiarServicio($s, EstadoServicio::EN_REPARACION, $usuario->usu_id,
                $reanuda ? 'Reparación reanudada' : 'Reparación iniciada');
        });
    }

    /**
     * Arma/actualiza las líneas de repuestos usados a partir de TODAS las
     * versiones aprobadas. osr_cantap = cantidad aprobada (tope); la
     * cantidad usada arranca igual a la aprobada y el técnico la baja si
     * usó menos.
     */
    public function sincronizarRepuestos(OrdenServicio $orden): void
    {
        foreach ($this->repuestosAprobados($orden) as $prdId => $r) {
            $osr = OrdenServicioRepuesto::where('osr_oseid', $orden->ose_id)->where('osr_prdid', $prdId)->first();

            if (!$osr) {
                OrdenServicioRepuesto::create([
                    'osr_oseid'  => $orden->ose_id,
                    'osr_prdid'  => $prdId,
                    'osr_depid'  => $r['depid'],
                    'osr_cantap' => $r['cantidad'],
                    'osr_cantid' => $r['cantidad'],
                    'osr_precio' => $r['precio'],
                    'osr_presup' => $r['original'],
                ]);
                continue;
            }

            // Un complementario sumó cantidad sobre un repuesto que ya estaba.
            $delta = $r['cantidad'] - (float) $osr->osr_cantap;
            if ($delta != 0.0) {
                $osr->update([
                    'osr_cantap' => $r['cantidad'],
                    'osr_cantid' => min($r['cantidad'], (float) $osr->osr_cantid + $delta),
                    'osr_precio' => $r['precio'],
                ]);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Repuestos usados (borrador del técnico)
    // ---------------------------------------------------------------------

    /** @param array<int, float> $cantidades  osr_id => cantidad usada */
    public function guardarRepuestos(OrdenServicio $orden, array $cantidades): void
    {
        $this->exigirEnReparacion($orden);

        DB::transaction(function () use ($orden, $cantidades) {
            foreach ($orden->repuestos as $osr) {
                if (!array_key_exists($osr->osr_id, $cantidades)) {
                    continue;
                }
                $cant = (float) $cantidades[$osr->osr_id];

                if ($cant < 0 || $cant > (float) $osr->osr_cantap) {
                    throw ValidationException::withMessages([
                        "repuestos.{$osr->osr_id}" => 'La cantidad usada debe estar entre 0 y la aprobada ('
                            . (float) $osr->osr_cantap . '). Para usar más, abra un presupuesto complementario.',
                    ]);
                }
                $osr->update(['osr_cantid' => $cant]);
            }
        });
    }

    // ---------------------------------------------------------------------
    // Finalizar y marcar como listo (UNA transacción)
    // ---------------------------------------------------------------------

    public function finalizar(OrdenServicio $orden, array $d, Usuario $usuario): OrdenServicio
    {
        $this->exigirEnReparacion($orden);
        $s = $orden->solicitud;

        return DB::transaction(function () use ($orden, $s, $d, $usuario) {
            // 0) Cantidades finales enviadas junto con el cierre.
            if (!empty($d['repuestos'])) {
                $this->guardarRepuestos($orden, $d['repuestos']);
                $orden->load('repuestos');
            }

            // 1) Liberar TODA la reserva de las versiones aprobadas...
            $aprobados = $this->presupuestosAprobados($orden);
            foreach ($aprobados as $p) {
                $this->stock->liberar($p);
            }

            // ...y consumir lo realmente usado: stock + kardex por línea.
            $impRepuestos = $subRepuestos = 0.0;
            foreach ($orden->repuestos()->with('producto.tipoIva')->get() as $osr) {
                $cant = (float) $osr->osr_cantid;
                if ($cant <= 0) {
                    continue;
                }

                if ($osr->producto?->prd_afestk) {
                    $this->consumir($osr, $orden, $usuario);
                }

                $total = round($cant * (float) $osr->osr_precio, 2);
                $subRepuestos += $total;
                $impRepuestos += Totales::ivaIncluido($total, (float) ($osr->producto?->tipoIva->tiv_tasa ?? 0));
            }

            // 2) Totales: mano de obra aprobada (todas las versiones) + repuestos usados.
            $manoObra = PresupuestoDetalle::whereIn('psd_pseid', $aprobados->pluck('pse_id'))
                ->where('psd_tipo', PresupuestoDetalle::MANO_OBRA);

            $this->totales->consolidar(
                $s,
                $subRepuestos + (float) $manoObra->clone()->sum('psd_total'),
                $impRepuestos + (float) $manoObra->clone()->sum('psd_iva'),
                $usuario->usu_id,
            );

            // 3) Cierre de la OT.
            $orden->update([
                'ose_fecfin' => now(),
                'ose_proced' => $d['ose_proced'],
                'ose_result' => $d['ose_result'],
                'ose_gardia' => (int) $d['ose_gardia'],
            ]);

            // 4) Estado + historial (la notificación al cliente llega en el Paso 6).
            $this->estados->cambiarServicio($s, EstadoServicio::LISTA, $usuario->usu_id,
                "Reparación finalizada ({$d['ose_result']}). Garantía del trabajo: {$d['ose_gardia']} días");

            return $orden->fresh();
        });
    }

    // ---------------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------------

    /** Salida de stock de UNA línea, con bloqueo de fila y registro en el kardex. */
    private function consumir(OrdenServicioRepuesto $osr, OrdenServicio $orden, Usuario $usuario): void
    {
        $cant  = (float) $osr->osr_cantid;
        $stock = Stock::where('sto_prdid', $osr->osr_prdid)
            ->where('sto_depid', $osr->osr_depid)
            ->lockForUpdate()
            ->first();

        // Reservas de OTRAS órdenes/pedidos siguen comprometidas: solo se usa lo libre.
        $libre = $stock ? (float) $stock->sto_cantid - (float) $stock->sto_reserv : 0.0;
        if ($libre < $cant) {
            throw ValidationException::withMessages([
                "repuestos.{$osr->osr_id}" => "Stock insuficiente de «{$osr->producto?->prd_descri}» "
                    . "(disponible: {$libre}). Registre la compra antes de finalizar.",
            ]);
        }

        $nuevaCantidad = (float) $stock->sto_cantid - $cant;
        $costo         = (float) $stock->sto_cosprm;   // promedio ponderado vigente (no se recalcula en salidas)

        $stock->sto_cantid = $nuevaCantidad;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_osrid'  => $osr->osr_id,
            'mst_usuid'  => $usuario->usu_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $cant,
            'mst_cosuni' => $costo,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $costo, 4),
            'mst_obs'    => "Servicio {$orden->ose_numero}",
            'mst_stprid' => $osr->osr_prdid,
            'mst_stdeid' => $osr->osr_depid,
        ]);
    }

    /** Presupuestos APROBADOS de la OT (v1 + complementarios aprobados). */
    private function presupuestosAprobados(OrdenServicio $orden): Collection
    {
        return $orden->presupuestos()
            ->whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::APROBADO))
            ->get();
    }

    /** @return array<int, array{cantidad: float, precio: float, depid: int, original: bool}> */
    private function repuestosAprobados(OrdenServicio $orden): array
    {
        $aprobados = $this->presupuestosAprobados($orden);

        $lineas = PresupuestoDetalle::whereIn('psd_pseid', $aprobados->pluck('pse_id'))
            ->where('psd_tipo', PresupuestoDetalle::REPUESTO)
            ->get();

        $versionDe = $aprobados->pluck('pse_versio', 'pse_id');
        $resultado = [];

        foreach ($lineas->groupBy('psd_prdid') as $prdId => $grupo) {
            $cantidad = (float) $grupo->sum('psd_cantid');
            $total    = (float) $grupo->sum('psd_total');

            $resultado[(int) $prdId] = [
                'cantidad' => $cantidad,
                // Si el mismo repuesto entró en dos versiones con precios distintos: promedio ponderado.
                'precio'   => $cantidad > 0 ? round($total / $cantidad, 2) : 0.0,
                'depid'    => (int) $grupo->first()->psd_depid,
                'original' => $grupo->contains(fn ($l) => (int) $versionDe[$l->psd_pseid] === 1),
            ];
        }

        return $resultado;
    }

    private function exigirEnReparacion(OrdenServicio $orden): void
    {
        $s = $orden->solicitud;

        if ($s->codigoServicio() !== EstadoServicio::EN_REPARACION || $orden->estaCerrada()) {
            throw ValidationException::withMessages(['ose_proced' => 'La orden no está en reparación.']);
        }

        $actual = $orden->presupuestoActual;
        if ($actual && !in_array($actual->codigoEstado(), [
            EstadoPresupuesto::APROBADO, EstadoPresupuesto::RECHAZADO, EstadoPresupuesto::VENCIDO,
        ], true)) {
            throw ValidationException::withMessages([
                'ose_proced' => "El presupuesto complementario {$actual->pse_numero} está pendiente: descártelo o espere la respuesta.",
            ]);
        }
    }
}
