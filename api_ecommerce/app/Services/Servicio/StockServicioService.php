<?php

namespace App\Services\Servicio;

use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\SolicitudServicio;

/**
 * Stock de repuestos del taller.
 *
 * Regla 1/2: presupuestar NO mueve stock (solo consulta); APROBAR reserva
 * (sto_reserv); CERRAR la OT consume (Paso 5). Nunca usa ajuste_inventario
 * (regla 3).
 */
class StockServicioService
{
    /**
     * Depósito del taller: el principal de la sucursal de la solicitud o, si
     * no tiene, el de la sucursal principal (mismo criterio que
     * PedidoService::resolverDeposito()).
     */
    public function depositoTaller(SolicitudServicio $s): ?Deposito
    {
        $sucursalId = $s->sse_sucid ?? Sucursal::where('suc_princi', 1)->value('suc_id');

        $deposito = $sucursalId
            ? Deposito::where('dpo_sucid', $sucursalId)->where('dpo_princi', 1)->first()
            : null;

        if (!$deposito) {
            $principal = Sucursal::where('suc_princi', 1)->value('suc_id');
            $deposito  = Deposito::where('dpo_sucid', $principal)->where('dpo_princi', 1)->first();
        }

        return $deposito;
    }

    /** Disponible = físico - reservado, por producto, en un depósito. */
    public function disponibles(array $productoIds, ?int $depositoId): array
    {
        if (!$depositoId || empty($productoIds)) {
            return [];
        }

        return Stock::where('sto_depid', $depositoId)
            ->whereIn('sto_prdid', $productoIds)
            ->get(['sto_prdid', 'sto_cantid', 'sto_reserv'])
            ->mapWithKeys(fn ($s) => [(int) $s->sto_prdid => (float) $s->sto_cantid - (float) $s->sto_reserv])
            ->all();
    }

    /**
     * Reserva los repuestos del presupuesto aprobado. Si una línea no tiene
     * stock suficiente NO se reserva (queda psd_reserv = false = "pendiente de
     * compra") en lugar de bloquear la aprobación del cliente.
     * lockForUpdate() evita que dos aprobaciones tomen la misma unidad.
     *
     * @return array<int, string> descripciones de los repuestos sin stock
     */
    public function reservar(PresupuestoServicio $p): array
    {
        $faltantes = [];

        foreach ($p->detalles()->with('producto')->get() as $linea) {
            if (!$linea->esRepuesto() || !$linea->producto?->prd_afestk) {
                continue;
            }

            $stock = Stock::where('sto_prdid', $linea->psd_prdid)
                ->where('sto_depid', $linea->psd_depid)
                ->lockForUpdate()
                ->first();

            $disponible = $stock ? (float) $stock->sto_cantid - (float) $stock->sto_reserv : 0.0;

            if ($stock && $disponible >= (float) $linea->psd_cantid) {
                $stock->increment('sto_reserv', (float) $linea->psd_cantid);
                $linea->update(['psd_reserv' => true]);
            } else {
                $faltantes[] = $linea->psd_descri;
            }
        }

        return $faltantes;
    }

    /** Libera lo reservado (OT cancelada o reemplazada). Solo toca líneas con psd_reserv. */
    public function liberar(PresupuestoServicio $p): void
    {
        foreach ($p->detalles()->where('psd_reserv', true)->get() as $linea) {
            $stock = Stock::where('sto_prdid', $linea->psd_prdid)
                ->where('sto_depid', $linea->psd_depid)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->sto_reserv = max(0, (float) $stock->sto_reserv - (float) $linea->psd_cantid);
                $stock->save();
            }

            $linea->update(['psd_reserv' => false]);
        }
    }

    /**
     * Anota en cada línea el precio de lista vigente y el disponible del
     * depósito (atributos no persistidos). Sirve al técnico (T3) y al admin
     * que valida: si el precio presupuestado difiere del de lista, se ve.
     */
    public function anotarReferencias(PresupuestoServicio $p): void
    {
        $p->loadMissing(['detalles.producto.tipoIva', 'detalles.trabajo']);

        $porDeposito = $p->detalles->where('psd_tipo', 'REPUESTO')->groupBy('psd_depid');
        $disponibles = [];
        foreach ($porDeposito as $depId => $lineas) {
            $disponibles[$depId] = $this->disponibles($lineas->pluck('psd_prdid')->all(), (int) $depId);
        }

        foreach ($p->detalles as $linea) {
            if ($linea->esRepuesto()) {
                $linea->setAttribute('precio_lista', $linea->producto?->precioPara((float) $linea->psd_cantid));
                $linea->setAttribute('disponible', $disponibles[$linea->psd_depid][$linea->psd_prdid] ?? 0.0);
            } else {
                $linea->setAttribute('precio_lista', $linea->trabajo ? (float) $linea->trabajo->trb_precio : null);
            }
        }
    }
}
