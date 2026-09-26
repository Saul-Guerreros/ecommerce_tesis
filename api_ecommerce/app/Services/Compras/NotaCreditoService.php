<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{
    NotaCredito, DetNotaCredito, CompraDetalle, MotivoNotaCredito,
    Stock, MovimientoStock, LibroCompra
};
use App\Services\Precio\PriceService;
use Illuminate\Support\Facades\{DB, Auth};

class NotaCreditoService
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

    /**
     * Registra la NC completa. El importe de cada linea NO llega del cliente:
     * se calcula acá a partir del precio facturado (motivos que afectan stock)
     * o del importe unitario ingresado (motivos de descuento).
     *
     * Criterio de IVA identico al de la factura: los importes son CON IVA
     * INCLUIDO y el IVA es el contenido (10% = /11, 5% = /21). Asi las filas
     * de factura y de NC se pueden sumar directo en libro_compra.
     */
    public function store(array $datosCab, array $lineas): NotaCredito
    {
        return DB::transaction(function () use ($datosCab, $lineas) {
            $motivo      = MotivoNotaCredito::findOrFail($datosCab['ncr_mncid']);
            $afectaStock = $motivo->afectaStock();

            $datosCab['ncr_usuid'] = Auth::id();
            $nota = NotaCredito::create($datosCab);

            // Importes por tasa, IVA incluido (mismo criterio que compra_cab).
            $subtotales = [0 => 0.0, 5 => 0.0, 10 => 0.0];

            foreach ($lineas as $linea) {
                $cmd  = CompraDetalle::with('compraCab')->findOrFail($linea['cmd_id']);
                $tasa = (float) $cmd->cmd_tasa;

                // cmd_canfac ya esta expresado en unidad de STOCK: no se
                // vuelve a convertir en ningun punto de este service.
                $cantidad   = round((float) $linea['cantidad'], 4);
                $precioNeto = $this->precioNetoPorUnidadDeStock($cmd);

                $importeUnit = $afectaStock
                    ? $precioNeto
                    : round((float) ($linea['descuento_unitario'] ?? 0), 2);

                if ($importeUnit <= 0) {
                    throw new \DomainException("No se pudo determinar el importe de la línea \"{$cmd->cmd_descri}\".");
                }

                if ($importeUnit > $precioNeto + 0.01) {
                    throw new \DomainException("El importe unitario supera el precio facturado de \"{$cmd->cmd_descri}\".");
                }

                $importe = round($importeUnit * $cantidad);          // Gs. sin decimales
                $iva     = $this->ivaIncluido($importe, $tasa);

                $dnc = DetNotaCredito::create([
                    'dnc_ncrid'              => $nota->ncr_id,
                    'dnc_cmdid'              => $cmd->cmd_id,
                    'dnc_cantidad'           => $cantidad,
                    'dnc_despor'             => $precioNeto > 0 ? round($importeUnit / $precioNeto * 100, 2) : 0,
                    'dnc_descuento_unitario' => $importeUnit,
                    'dnc_base_gravada'       => $importe,            // IVA incluido
                    'dnc_iva'                => $iva,                // IVA contenido
                    'dnc_total'              => $importe,
                    'dnc_canaju'             => 0,
                ]);

                if ($afectaStock) {
                    $this->registrarDevolucion($cmd, $dnc, $cantidad, $nota->ncr_numero);
                } else {
                    $this->ajustarCostoStock($cmd, $dnc, $cantidad, $importe, $tasa, $nota->ncr_numero);
                }

                $subtotales[(int) $tasa] = ($subtotales[(int) $tasa] ?? 0) + $importe;
            }

            $nota->ncr_exenta  = $subtotales[0];
            $nota->ncr_grav5   = $subtotales[5];
            $nota->ncr_grav10  = $subtotales[10];
            $nota->ncr_iva5    = $this->ivaIncluido($subtotales[5], 5);
            $nota->ncr_iva10   = $this->ivaIncluido($subtotales[10], 10);
            $nota->ncr_gravada = $subtotales[0] + $subtotales[5] + $subtotales[10];
            $nota->ncr_iva     = $nota->ncr_iva5 + $nota->ncr_iva10;
            $nota->ncr_total   = $nota->ncr_gravada;   // IVA ya incluido
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
                // En NEGATIVO: SUM(lcm_*) da el neto del periodo.
                'lcm_exenta' => -$nota->ncr_exenta,
                'lcm_grav5'  => -$nota->ncr_grav5,
                'lcm_grav10' => -$nota->ncr_grav10,
                'lcm_iva5'   => -$nota->ncr_iva5,
                'lcm_iva10'  => -$nota->ncr_iva10,
                'lcm_total'  => -$nota->ncr_total,
            ]);

            return $nota->fresh(['motivo', 'detalle.compraDetalle.producto']);
        });
    }

    /** Precio facturado neto de descuento, por unidad de STOCK, con IVA incluido. */
    private function precioNetoPorUnidadDeStock(CompraDetalle $cmd): float
    {
        $factor = max((float) $cmd->cmd_factconv, 0.0001);

        return round(((float) $cmd->cmd_precun - (float) $cmd->cmd_descto) / $factor, 4);
    }

    /** IVA contenido en un monto con IVA incluido: 10% = /11, 5% = /21. */
    private function ivaIncluido(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa)) : 0.0;
    }

    // =================================================================
    //  A) Motivos que AFECTAN stock (ECAN, DDEF, DOTR)
    // =================================================================

    /**
     * Devolucion al proveedor: salida fisica valorizada al costo promedio
     * vigente. El CPP no se recalcula (misma convencion que la anulacion de
     * compra): sale mercaderia al costo con el que esta valuada.
     */
    private function registrarDevolucion(
        CompraDetalle $cmd,
        DetNotaCredito $dnc,
        float $cantidad,
        string $numeroNC
    ): void {
        $depositoId = $cmd->compraCab->com_depid;

        $stock = Stock::where('sto_prdid', $cmd->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->first();

        $disponible = $stock ? (float) $stock->sto_cantid - (float) $stock->sto_reserv : 0.0;

        if ($disponible < $cantidad - 0.0001) {
            throw new \DomainException(
                "No se puede registrar la devolución de \"{$cmd->cmd_descri}\": " .
                "stock disponible {$disponible}, se requieren {$cantidad}."
            );
        }

        $costoPromedio = (float) $stock->sto_cosprm;
        $nuevaCantidad = round((float) $stock->sto_cantid - $cantidad, 4);

        $stock->sto_cantid = $nuevaCantidad;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_cmdid'  => $cmd->cmd_id,
            'mst_dncid'  => $dnc->dnc_id,
            'mst_usuid'  => Auth::id(),
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $cantidad,
            'mst_cosuni' => $costoPromedio,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $costoPromedio, 4),
            'mst_obs'    => "Devolución al proveedor s/Nota de Crédito {$numeroNC}",
            'mst_stprid' => $cmd->cmd_prdid,
            'mst_stdeid' => $depositoId,
        ]);

        $dnc->dnc_canaju = $cantidad;
        $dnc->save();
    }

    // =================================================================
    //  B) Motivos que NO afectan stock (DESC, EPRE)
    // =================================================================

    /**
     * La cantidad de stock no se toca: solo baja el VALOR del saldo todavia
     * disponible, y con el el costo promedio. Lo ya vendido conserva su costo
     * historico: el kardex nunca se reescribe.
     *
     * El costo de stock se lleva SIN IVA (el IVA de compra es credito fiscal),
     * por eso el importe acreditado se neutraliza de IVA antes de aplicarlo.
     */
    private function ajustarCostoStock(
        CompraDetalle $cmd,
        DetNotaCredito $dnc,
        float $cantidad,
        float $importeConIva,
        float $tasa,
        string $numeroNC
    ): void {
        $depositoId   = $cmd->compraCab->com_depid;
        $importeSinIva = $tasa > 0 ? $importeConIva / (1 + $tasa / 100) : $importeConIva;
        $descuentoUnitario = round($importeSinIva / $cantidad, 4);   // por unidad de stock

        $stock = Stock::where('sto_prdid', $cmd->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->first();

        if (!$stock || (float) $stock->sto_cantid <= 0) {
            return; // ya no queda nada de esa compra en stock
        }

        $cantidadAjustable = min((float) $stock->sto_cantid, $cantidad);

        if ($cantidadAjustable <= 0) {
            return;
        }

        $valorActual       = round((float) $stock->sto_cantid * (float) $stock->sto_cosprm, 4);
        $descuentoAplicado = round($descuentoUnitario * $cantidadAjustable, 4);
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
            'mst_obs'    => "Ajuste de costo s/Nota de Crédito {$numeroNC}",
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