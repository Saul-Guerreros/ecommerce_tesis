<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{AjusteInventario, AjusteInventarioDetalle, Stock, MovimientoStock};
use Illuminate\Support\Facades\{DB, Auth};

class AjusteService
{
    /**
     * Crea el ajuste en estado Borrador. NO toca stock todavia -- es
     * el "conteo fisico" cargado, pendiente de revision.
     *
     * @param array $lineas cada una con: prd_id, cantfis, costo (opcional,
     *                      solo se usa si termina siendo un sobrante), obs.
     */
    public function store(array $datosCab, array $lineas, int $depositoId): AjusteInventario
    {
        return DB::transaction(function () use ($datosCab, $lineas, $depositoId) {
            $datosCab['aji_estado'] = AjusteInventario::ESTADO_BORRADOR;
            $datosCab['aji_usuid']  = Auth::id();

            $ajuste = AjusteInventario::create($datosCab);

            foreach ($lineas as $linea) {
                // aid_cantsis se captura AHORA, a modo informativo para quien
                // revise el borrador. Al confirmar se vuelve a leer en vivo.
                $stockActual = Stock::where('sto_prdid', $linea['prd_id'])
                    ->where('sto_depid', $depositoId)
                    ->value('sto_cantid');

                AjusteInventarioDetalle::create([
                    'aid_ajiid'   => $ajuste->aji_id,
                    'aid_prdid'   => $linea['prd_id'],
                    'aid_cantsis' => $stockActual ?? 0,
                    'aid_cantfis' => $linea['cantfis'],
                    'aid_costo'   => $linea['costo'] ?? null,
                    'aid_obs'     => $linea['obs'] ?? null,
                ]);
            }

            return $ajuste->load('detalle');
        });
    }

    /**
     * Confirma el ajuste: recien aca se toca stock/kardex. Por cada linea,
     * relee sto_cantid EN VIVO (no confia en aid_cantsis del borrador) y
     * aplica la diferencia contra ese valor actualizado.
     */
    public function confirmar(AjusteInventario $ajuste): AjusteInventario
    {
        if ($ajuste->aji_estado !== AjusteInventario::ESTADO_BORRADOR) {
            throw new \DomainException('Solo se puede confirmar un ajuste en estado Borrador.');
        }

        return DB::transaction(function () use ($ajuste) {
            $ajuste->load('detalle');

            foreach ($ajuste->detalle as $detalle) {
                $this->aplicarDiferencia($detalle, $ajuste->aji_depid);
            }

            $ajuste->aji_estado = AjusteInventario::ESTADO_CONFIRMADO;
            $ajuste->aji_confir = now();
            $ajuste->aji_usconf = Auth::id();
            $ajuste->save();

            return $ajuste->fresh('detalle');
        });
    }

    private function aplicarDiferencia(AjusteInventarioDetalle $detalle, int $depositoId): void
    {
        $stock = Stock::where('sto_prdid', $detalle->aid_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->first();

        if (!$stock) {
            $stock = new Stock([
                'sto_prdid'  => $detalle->aid_prdid,
                'sto_depid'  => $depositoId,
                'sto_cantid' => 0,
                'sto_reserv' => 0,
                'sto_cosprm' => 0,
            ]);
        }

        // Se recaptura EN VIVO, no se usa aid_cantsis del borrador.
        $cantidadActual = (float) $stock->sto_cantid;
        $cantidadFisica = (float) $detalle->aid_cantfis;
        $diferencia     = round($cantidadFisica - $cantidadActual, 4);

        // Se actualiza aid_cantsis con el valor real usado al confirmar,
        // para que el detalle quede como historial fiel de lo que paso.
        $detalle->aid_cantsis = $cantidadActual;

        if ($diferencia == 0) {
            $detalle->save();
            return;
        }

        if ($diferencia > 0) {
            // Sobrante -> Entrada. Requiere costo para calcular el CPP.
            if ($detalle->aid_costo === null) {
                throw new \DomainException(
                    "El producto \"{$detalle->producto?->prd_descri}\" tiene un sobrante mayor a stock; falta indicar el costo de esa entrada."
                );
            }

            $costoEntrada  = (float) $detalle->aid_costo;
            $nuevaCantidad = $cantidadActual + $diferencia;
            $nuevoCosto = $nuevaCantidad > 0
                ? round((($cantidadActual * (float) $stock->sto_cosprm) + ($diferencia * $costoEntrada)) / $nuevaCantidad, 4)
                : 0;

            $stock->sto_cantid = $nuevaCantidad;
            $stock->sto_cosprm = $nuevoCosto;
            $stock->sto_actual = now();
            $stock->save();

            MovimientoStock::create([
                'mst_aidid'  => $detalle->aid_id,
                'mst_usuid'  => Auth::id(),
                'mst_fecha'  => now(),
                'mst_tipo'   => 'E',
                'mst_cantid' => $diferencia,
                'mst_cosuni' => $costoEntrada,
                'mst_salcan' => $nuevaCantidad,
                'mst_salcos' => round($nuevaCantidad * $nuevoCosto, 4),
                'mst_obs'    => 'Ajuste de inventario (sobrante)',
                'mst_stprid' => $detalle->aid_prdid,
                'mst_stdeid' => $depositoId,
            ]);

            $this->recalcularPrecioVenta($detalle->aid_prdid, $nuevoCosto);
        } else {
            // Faltante -> Salida. NO recalcula el CPP.
            $cantidadSalida = abs($diferencia);

            if ($cantidadSalida > $cantidadActual) {
                throw new \DomainException(
                    "El producto \"{$detalle->producto?->prd_descri}\" no tiene stock suficiente para el ajuste cargado."
                );
            }

            $nuevaCantidad = $cantidadActual - $cantidadSalida;
            $costoVigente  = (float) $stock->sto_cosprm;

            $stock->sto_cantid = $nuevaCantidad;
            $stock->sto_actual = now();
            $stock->save();

            MovimientoStock::create([
                'mst_aidid'  => $detalle->aid_id,
                'mst_usuid'  => Auth::id(),
                'mst_fecha'  => now(),
                'mst_tipo'   => 'S',
                'mst_cantid' => $cantidadSalida,
                'mst_cosuni' => $costoVigente,
                'mst_salcan' => $nuevaCantidad,
                'mst_salcos' => round($nuevaCantidad * $costoVigente, 4),
                'mst_obs'    => 'Ajuste de inventario (faltante)',
                'mst_stprid' => $detalle->aid_prdid,
                'mst_stdeid' => $depositoId,
            ]);
        }

        $detalle->save();
    }

    private function recalcularPrecioVenta(int $productoId, float $costoPromedio): void
    {
        $producto = Producto::find($productoId);

        if (!$producto || $producto->prd_margen === null) {
            return;
        }

        $margen = (float) $producto->prd_margen;
        $producto->prd_prevta = round($costoPromedio * (1 + $margen / 100), 2);
        $producto->save();
    }

    /**
     * Anula un ajuste ya confirmado. Misma regla que Compra: solo si no hay
     * movimientos posteriores para ninguno de los productos involucrados.
     */
    public function anular(AjusteInventario $ajuste): AjusteInventario
    {
        if ($ajuste->aji_estado !== AjusteInventario::ESTADO_CONFIRMADO) {
            throw new \DomainException('Solo se puede anular un ajuste Confirmado.');
        }

        return DB::transaction(function () use ($ajuste) {
            $ajuste->load('detalle');

            foreach ($ajuste->detalle as $detalle) {
                $this->revertirDiferencia($detalle, $ajuste->aji_depid);
            }

            $ajuste->aji_estado  = AjusteInventario::ESTADO_ANULADO;
            $ajuste->aji_anulado = now();
            $ajuste->aji_usanul  = Auth::id();
            $ajuste->save();

            return $ajuste->fresh('detalle');
        });
    }

    private function revertirDiferencia(AjusteInventarioDetalle $detalle, int $depositoId): void
    {
        $movimiento = MovimientoStock::where('mst_aidid', $detalle->aid_id)->first();

        if (!$movimiento) {
            return;
        }

        $hayPosteriores = MovimientoStock::where('mst_stprid', $detalle->aid_prdid)
            ->where('mst_stdeid', $depositoId)
            ->where('mst_id', '>', $movimiento->mst_id)
            ->exists();

        if ($hayPosteriores) {
            throw new \DomainException(
                "No se puede anular: el producto \"{$detalle->producto?->prd_descri}\" tiene movimientos de stock posteriores a este ajuste."
            );
        }

        $stock = Stock::where('sto_prdid', $detalle->aid_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->firstOrFail();

        $anterior = MovimientoStock::where('mst_stprid', $detalle->aid_prdid)
            ->where('mst_stdeid', $depositoId)
            ->where('mst_id', '<', $movimiento->mst_id)
            ->orderByDesc('mst_id')
            ->first();

        $cantidadRestaurada = $anterior ? (float) $anterior->mst_salcan : 0;
        $costoRestaurado    = $anterior && $cantidadRestaurada > 0
            ? round(((float) $anterior->mst_salcos) / $cantidadRestaurada, 4)
            : 0;

        $stock->sto_cantid = $cantidadRestaurada;
        $stock->sto_cosprm = $costoRestaurado;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_aidid'  => $detalle->aid_id,
            'mst_usuid'  => Auth::id(),
            'mst_revid'  => $movimiento->mst_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => $movimiento->mst_tipo === 'E' ? 'S' : 'E',
            'mst_cantid' => $movimiento->mst_cantid,
            'mst_cosuni' => $movimiento->mst_cosuni,
            'mst_salcan' => $cantidadRestaurada,
            'mst_salcos' => round($cantidadRestaurada * $costoRestaurado, 4),
            'mst_obs'    => 'Reversa por anulación de ajuste',
            'mst_stprid' => $detalle->aid_prdid,
            'mst_stdeid' => $depositoId,
        ]);

        $this->recalcularPrecioVenta($detalle->aid_prdid, $costoRestaurado);
    }

    /**
     * Un Borrador nunca toco stock: se puede eliminar directo.
     */
    public function eliminarBorrador(AjusteInventario $ajuste): void
    {
        if ($ajuste->aji_estado !== AjusteInventario::ESTADO_BORRADOR) {
            throw new \DomainException('Solo se puede eliminar un ajuste en estado Borrador.');
        }

        $ajuste->delete();
    }
}