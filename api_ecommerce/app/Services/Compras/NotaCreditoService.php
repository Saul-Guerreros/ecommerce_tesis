<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{NotaCredito, DetNotaCredito, CompraDetalle, Stock, MovimientoStock, LibroCompra};
use App\Services\Precio\PriceService;
use Illuminate\Support\Facades\{DB, Auth};

class NotaCreditoService
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

    public function store(array $datosCab, array $lineas): NotaCredito
    {
        return DB::transaction(function () use ($datosCab, $lineas) {
            $datosCab['ncr_usuid'] = Auth::id();
            $nota = NotaCredito::create($datosCab);

            $exentaTotal = $grav5Total = $grav10Total = $iva5Total = $iva10Total = 0.0;

            foreach ($lineas as $linea) {
                $detalleCompra = CompraDetalle::with('compraCab')->findOrFail($linea['cmd_id']);
                $tasa = (float) $detalleCompra->cmd_tasa;

                $cantidad    = (float) $linea['cantidad'];
                $baseGravada = (float) $linea['base_gravada'];
                $iva         = (float) ($linea['iva'] ?? round($baseGravada * $tasa / 100, 2));
                $total       = round($baseGravada + $iva, 2);

                $detalleNC = DetNotaCredito::create([
                    'dnc_ncrid'        => $nota->ncr_id,
                    'dnc_cmdid'        => $detalleCompra->cmd_id,
                    'dnc_cantidad'     => $cantidad,
                    'dnc_base_gravada' => $baseGravada,
                    'dnc_iva'          => $iva,
                    'dnc_total'        => $total,
                    'dnc_canaju'       => 0,
                ]);

                $this->ajustarCostoStock($detalleCompra, $detalleNC, $cantidad, $baseGravada, $nota->ncr_numero);

                if ($tasa == 10) {
                    $grav10Total += $baseGravada;
                    $iva10Total  += $iva;
                } elseif ($tasa == 5) {
                    $grav5Total += $baseGravada;
                    $iva5Total  += $iva;
                } else {
                    $exentaTotal += $baseGravada;
                }
            }

            $nota->ncr_exenta  = $exentaTotal;
            $nota->ncr_grav5   = $grav5Total;
            $nota->ncr_grav10  = $grav10Total;
            $nota->ncr_iva5    = $iva5Total;
            $nota->ncr_iva10   = $iva10Total;
            $nota->ncr_gravada = $exentaTotal + $grav5Total + $grav10Total;
            $nota->ncr_iva     = $iva5Total + $iva10Total;
            $nota->ncr_total   = round($nota->ncr_gravada + $nota->ncr_iva, 2);
            $nota->save();

            $compraCab = $nota->compraCab()->first();

            LibroCompra::create([
                'lcm_tipo'   => LibroCompra::TIPO_NOTA_CREDITO,
                'lcm_ncrid'  => $nota->ncr_id,
                'lcm_estado' => LibroCompra::ESTADO_VIGENTE,
                'lcm_fecha'  => $nota->ncr_fecha,
                'lcm_nrodoc' => $nota->ncr_numero,
                'lcm_proraz' => $compraCab->com_proraz,
                'lcm_proruc' => $compraCab->com_proruc,
                // En NEGATIVO: asi SUM(lcm_*) en libro_compra da el neto del periodo.
                'lcm_exenta' => -$exentaTotal,
                'lcm_grav5'  => -$grav5Total,
                'lcm_grav10' => -$grav10Total,
                'lcm_iva5'   => -$iva5Total,
                'lcm_iva10'  => -$iva10Total,
                'lcm_total'  => -$nota->ncr_total,
            ]);

            return $nota->fresh('detalle.compraDetalle.producto');
        });
    }

    /**
     * Reparte el descuento (base_gravada) entre la cantidad acreditada, lo
     * convierte a costo por unidad de STOCK (mismo criterio que
     * CompraService::resolverConversion) y baja sto_cosprm SOLO en la
     * proporcion que todavia esta fisicamente en stock. Lo ya vendido
     * conserva su costo historico: el kardex nunca se reescribe.
     */
    private function ajustarCostoStock(
        CompraDetalle $cmd,
        DetNotaCredito $dnc,
        float $cantidadFacturada,
        float $baseGravada,
        string $numeroNC
    ): void {
        $cantidadStock      = round($cantidadFacturada * (float) $cmd->cmd_factconv, 4);
        $descuentoUnitStock = round(($baseGravada / $cantidadFacturada) / (float) $cmd->cmd_factconv, 4);
        $depositoId         = $cmd->compraCab->com_depid;

        $stock = Stock::where('sto_prdid', $cmd->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->first();

        if (!$stock || (float) $stock->sto_cantid <= 0) {
            return; // ya no queda nada de esa compra en stock
        }

        $cantidadAjustable = min((float) $stock->sto_cantid, $cantidadStock);

        if ($cantidadAjustable <= 0) {
            return;
        }

        $valorActual       = round((float) $stock->sto_cantid * (float) $stock->sto_cosprm, 4);
        $descuentoAplicado = round($descuentoUnitStock * $cantidadAjustable, 4);
        $nuevoValor        = max(0, $valorActual - $descuentoAplicado);
        $nuevoCosprm       = round($nuevoValor / (float) $stock->sto_cantid, 4);

        $stock->sto_cosprm = $nuevoCosprm;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_cmdid'  => $cmd->cmd_id,
            'mst_dncid'  => $dnc->dnc_id,
            'mst_usuid'  => Auth::id(),
            'mst_fecha'  => now(),
            'mst_tipo'   => 'A',
            'mst_cantid' => $cantidadAjustable,
            'mst_cosuni' => $nuevoCosprm,
            'mst_salcan' => $stock->sto_cantid,
            'mst_salcos' => round((float) $stock->sto_cantid * $nuevoCosprm, 4),
            'mst_obs'    => "Ajuste de costo por Nota de Crédito {$numeroNC}",
            'mst_stprid' => $cmd->cmd_prdid,
            'mst_stdeid' => $depositoId,
        ]);

        $dnc->dnc_canaju = $cantidadAjustable;
        $dnc->save();

        $producto = Producto::with('tipoIva')->find($cmd->cmd_prdid);
        if ($producto) {
            $this->priceService->aplicarPrecios($producto, $nuevoCosprm);
        }
    }
}