<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{CompraCab, CompraDetalle, Stock, MovimientoStock};
use Illuminate\Support\Facades\{DB, Auth};

class CompraService
{
    /**
     * Guarda la factura completa (cabecera + detalle) e impacta el stock
     * en la MISMA transaccion: en este flujo no existe un paso de
     * "confirmar" separado, Guardar ya deja la compra Confirmada.
     */
    public function store(array $datosCab, array $lineas): CompraCab
    {
        return DB::transaction(function () use ($datosCab, $lineas) {
            $datosCab['com_estfac'] = CompraCab::ESTADO_CONFIRMADA;
            $datosCab['com_usuid']  = Auth::id();

            $compra = CompraCab::create($datosCab);

            foreach ($lineas as $orden => $linea) {
                $detalle = CompraDetalle::create([
                    'cmd_comid'   => $compra->com_id,
                    'cmd_prdid'   => $linea['prd_id'],
                    'cmd_tivid'   => $linea['tiv_id'],
                    'cmd_nroprd'  => $orden + 1,
                    'cmd_descri'  => $linea['descri'],
                    'cmd_costo'   => $linea['costo'],
                    'cmd_despor'  => $linea['despor']  ?? 0,
                    'cmd_descto'  => $linea['descto']  ?? 0,
                    'cmd_tasa'    => $linea['tasa']    ?? 0,
                    'cmd_gravada' => $linea['gravada'] ?? 0,
                    'cmd_iva'     => $linea['iva']     ?? 0,
                    'cmd_canfac'  => $linea['canfac'],
                    'cmd_canrec'  => $linea['canrec'],
                ]);

                if ((float) $detalle->cmd_canrec > 0) {
                    $this->registrarEntrada($detalle, $datosCab['com_depid']);
                }
            }

            return $compra->load('detalle');
        });
    }

    /**
     * Inserta el movimiento de Entrada, recalcula el CPP en 'stock' y
     * recalcula producto.prd_prevta. Se llama una vez por linea con
     * cantidad recibida > 0.
     */
    private function registrarEntrada(CompraDetalle $detalle, int $depositoId): void
    {
        // lockForUpdate(): si dos compras del mismo producto/deposito se
        // confirman al mismo tiempo, la segunda transaccion espera a que
        // la primera termine antes de leer sto_cantid/sto_cosprm. Sin esto,
        // ambas leerian el mismo valor "viejo" y una de las dos pisaria
        // el calculo de la otra.
        $stock = Stock::where('sto_prdid', $detalle->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->first();

        if (!$stock) {
            $stock = new Stock([
                'sto_prdid'  => $detalle->cmd_prdid,
                'sto_depid'  => $depositoId,
                'sto_cantid' => 0,
                'sto_reserv' => 0,
                'sto_cosprm' => 0,
            ]);
        }

        $cantidadAnterior = (float) $stock->sto_cantid;
        $costoAnterior    = (float) $stock->sto_cosprm;
        $cantidadEntrada  = (float) $detalle->cmd_canrec;
        $costoEntrada     = (float) $detalle->cmd_costo;

        $nuevaCantidad = $cantidadAnterior + $cantidadEntrada;

        // Formula del CPP: (stock_ant * costo_ant + cant_entrada * costo_entrada) / stock_resultante
        $nuevoCosto = $nuevaCantidad > 0
            ? round((($cantidadAnterior * $costoAnterior) + ($cantidadEntrada * $costoEntrada)) / $nuevaCantidad, 4)
            : 0;

        $stock->sto_cantid = $nuevaCantidad;
        $stock->sto_cosprm = $nuevoCosto;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_cmdid'  => $detalle->cmd_id,
            'mst_usuid'  => Auth::id(),
            'mst_fecha'  => now(),
            'mst_tipo'   => 'E',
            'mst_cantid' => $cantidadEntrada,
            'mst_cosuni' => $costoEntrada,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $nuevoCosto, 4),
            'mst_stprid' => $detalle->cmd_prdid,
            'mst_stdeid' => $depositoId,
        ]);

        $this->recalcularPrecioVenta($detalle->cmd_prdid, $nuevoCosto);
    }

    /**
     * prd_prevta = sto_cosprm * (1 + prd_margen / 100).
     * Si el producto no tiene margen definido, no se toca el precio: no
     * hay base para calcular nada, y forzar un valor inventado violaria
     * la regla de "no inventar datos".
     */
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
     * Anula una compra confirmada. Solo se permite si NINGUNA linea de la
     * compra tiene movimientos de stock posteriores (para cualquier otro
     * origen: otra compra, un ajuste, etc.) — si los hay, deshacer esta
     * entrada dejaria el CPP posterior calculado sobre una base que ya no
     * existe. En ese caso se bloquea con un mensaje explicando cual
     * producto lo impide.
     */
    public function anular(CompraCab $compra): CompraCab
    {
        if ($compra->com_estfac === CompraCab::ESTADO_ANULADA) {
            throw new \DomainException('La compra ya está anulada.');
        }

        return DB::transaction(function () use ($compra) {
            $compra->load('detalle');

            foreach ($compra->detalle as $detalle) {
                if ((float) $detalle->cmd_canrec <= 0) {
                    continue;
                }

                $this->revertirEntrada($detalle, $compra->com_depid);
            }

            $compra->com_estfac = CompraCab::ESTADO_ANULADA;
            $compra->com_fecanu = now();
            $compra->com_anusid = Auth::id();
            $compra->save();

            return $compra->fresh('detalle');
        });
    }

    private function revertirEntrada(CompraDetalle $detalle, int $depositoId): void
    {
        $entrada = MovimientoStock::where('mst_cmdid', $detalle->cmd_id)
            ->where('mst_tipo', 'E')
            ->first();

        if (!$entrada) {
            return;
        }

        // Regla de seguridad: si existe CUALQUIER movimiento posterior a
        // esta entrada para el mismo producto+deposito, no se puede anular
        // sin invalidar el CPP calculado despues de ese punto.
        $hayPosteriores = MovimientoStock::where('mst_stprid', $detalle->cmd_prdid)
            ->where('mst_stdeid', $depositoId)
            ->where('mst_id', '>', $entrada->mst_id)
            ->exists();

        if ($hayPosteriores) {
            throw new \DomainException(
                "No se puede anular: el producto \"{$detalle->cmd_descri}\" tiene movimientos de stock posteriores a esta compra."
            );
        }

        $stock = Stock::where('sto_prdid', $detalle->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->firstOrFail();

        // Como confirmamos que esta ES la ultima entrada, el estado previo
        // es exactamente el saldo del movimiento anterior (o cero si esta
        // fue la primera vez que se compro este producto).
        $anterior = MovimientoStock::where('mst_stprid', $detalle->cmd_prdid)
            ->where('mst_stdeid', $depositoId)
            ->where('mst_id', '<', $entrada->mst_id)
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
            'mst_cmdid'  => $detalle->cmd_id,
            'mst_usuid'  => Auth::id(),
            'mst_revid'  => $entrada->mst_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $detalle->cmd_canrec,
            'mst_cosuni' => $entrada->mst_cosuni,
            'mst_salcan' => $cantidadRestaurada,
            'mst_salcos' => round($cantidadRestaurada * $costoRestaurado, 4),
            'mst_obs'    => 'Reversa por anulación de compra',
            'mst_stprid' => $detalle->cmd_prdid,
            'mst_stdeid' => $depositoId,
        ]);

        $this->recalcularPrecioVenta($detalle->cmd_prdid, $costoRestaurado);
    }
}