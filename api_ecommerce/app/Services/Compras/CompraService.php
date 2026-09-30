<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{CompraCab, CompraDetalle, Stock, MovimientoStock, LibroCompra, CuotaCompra, PagoCompra};
use App\Services\Precio\PriceService;
use Illuminate\Support\Facades\{DB, Auth};

class CompraService
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

    /**
     * Guarda la factura completa (cabecera + detalle), impacta el stock y
     * registra la fila del Libro de Compras, todo en la MISMA transaccion.
     */
        public function store(array $datosCab, array $lineas): CompraCab
    {
        return DB::transaction(function () use ($datosCab, $lineas) {
            $datosCab['com_estfac'] = CompraCab::ESTADO_CONFIRMADA;
            $datosCab['com_usuid']  = Auth::id();

            $compra = CompraCab::create($datosCab);

            // Subtotales por tasa, IVA INCLUIDO (como figuran en la factura).
            $subtotales = [0 => 0.0, 5 => 0.0, 10 => 0.0];

            foreach ($lineas as $orden => $linea) {
                $precio   = (float) $linea['precun'];
                $cantidad = (float) $linea['canfac'];
                // La tasa sale de tipo_iva, nunca del cliente.
                $tasa = (float) DB::table('tipo_iva')->where('tiv_id', $linea['tiv_id'])->value('tiv_tasa');

                // Fuente de verdad: descuento UNITARIO (Gs.). El % se deriva.
                $descUnit = $this->descuentoUnitario($linea, $precio);
                $despor   = $precio > 0 ? round($descUnit / $precio * 100, 2) : 0;

                $precioNeto = $precio - $descUnit;                   // unitario, IVA incluido
               $totalLinea = round($precioNeto * $cantidad);     // "total operacion" (Gs. no tiene decimales)
                $ivaLinea   = $this->ivaIncluido($totalLinea, $tasa); // informativo por linea

                // El IVA de compra es credito fiscal: el costo entra SIN IVA
                // (PriceService agrega margen e IVA para el precio de venta).
                $precioSinIva = $tasa > 0 ? $precioNeto / (1 + $tasa / 100) : $precioNeto;

                [$factor, $costoUnitStock, $equivalenciaId] = $this->resolverConversion(
                    (int) $linea['prd_id'], (int) $linea['umd_id'], $linea['peq_id'] ?? null, $precioSinIva
                );

                $detalle = CompraDetalle::create([
                    'cmd_comid'    => $compra->com_id,
                    'cmd_prdid'    => $linea['prd_id'],
                    'cmd_umdid'    => $linea['umd_id'],
                    'cmd_peqid'    => $equivalenciaId,
                    'cmd_factconv' => $factor,
                    'cmd_tivid'    => $linea['tiv_id'],
                    'cmd_nroprd'   => $orden + 1,
                    'cmd_descri'   => $linea['descri'],
                    'cmd_precun'   => $precio,          // precio impreso, sin descuento
                    'cmd_costo'    => $costoUnitStock,  // neto de descuento y de IVA, en unidad de stock
                    'cmd_despor'   => $despor,
                    'cmd_descto'   => $descUnit,        // descuento UNITARIO
                    'cmd_tasa'     => $tasa,
                    'cmd_gravada'  => $totalLinea,      // total de la linea, IVA incluido
                    'cmd_iva'      => $ivaLinea,
                    'cmd_canfac'   => round($cantidad * $factor, 4),
                    'cmd_canrec'   => round((float) $linea['canrec'] * $factor, 4),
                ]);

                $subtotales[(int) $tasa] = ($subtotales[(int) $tasa] ?? 0) + $totalLinea;

                if ((float) $detalle->cmd_canrec > 0) {
                    $this->registrarEntrada($detalle, $datosCab['com_depid']);
                }
            }

            // Liquidacion del IVA sobre los subtotales: 10% = /11, 5% = /21.
            $compra->update([
                'com_exenta' => $subtotales[0],
                'com_grav5'  => $subtotales[5],
                'com_grav10' => $subtotales[10],
                'com_iva5'   => $this->ivaIncluido($subtotales[5], 5),
                'com_iva10'  => $this->ivaIncluido($subtotales[10], 10),
                'com_total'  => $subtotales[0] + $subtotales[5] + $subtotales[10],
            ]);

            $compra->refresh();

            $this->generarCuotas($compra);

            LibroCompra::create([
                'lcm_tipo'   => LibroCompra::TIPO_FACTURA,
                'lcm_comid'  => $compra->com_id,
                'lcm_estado' => LibroCompra::ESTADO_VIGENTE,
                'lcm_fecha'  => $compra->com_fchaem,
                'lcm_nrodoc' => $compra->com_nrodoc,
                'lcm_proraz' => $compra->com_proraz,
                'lcm_proruc' => $compra->com_proruc,
                'lcm_exenta' => $compra->com_exenta,
                'lcm_grav5'  => $compra->com_grav5,
                'lcm_grav10' => $compra->com_grav10,
                'lcm_iva5'   => $compra->com_iva5,
                'lcm_iva10'  => $compra->com_iva10,
                'lcm_total'  => $compra->com_total,
            ]);

            return $compra->load('detalle');
        });
    }

    /** Si llega el monto se usa tal cual; si solo llega el %, se calcula el monto. */
    private function descuentoUnitario(array $linea, float $precio): float
    {
        if (isset($linea['descto']) && $linea['descto'] !== '') {
            return round((float) $linea['descto'], 2);
        }
        return round($precio * (float) ($linea['despor'] ?? 0) / 100, 2);
    }

    /** IVA contenido en un monto con IVA incluido: tasa/(100+tasa) → 10% = /11, 5% = /21. */
    private function ivaIncluido(float $monto, float $tasa): float
    {
        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa)) : 0.0;
    }

    /**
     * Resuelve el factor producto-unidad y el costo por unidad de STOCK a
     * partir del precio unitario (ya neto de descuento) facturado en la
     * unidad elegida.
     */
    private function resolverConversion(int $productoId, int $unidadId, ?int $equivalenciaId, float $precioUnitario): array
    {
        $producto = Producto::findOrFail($productoId);

        if ($unidadId === (int) $producto->prd_umdid) {
            $factor = 1.0;
            $peqId  = null;
        } else {
            $equivalencia = \App\Models\Compras\ProductoEquivalencia::where('peq_id', $equivalenciaId)
                ->where('peq_prdid', $productoId)
                ->where('peq_umdid', $unidadId)
                ->where('peq_estado', 1)
                ->first();

            if (!$equivalencia) {
                throw new \DomainException('No existe una equivalencia activa para la presentación seleccionada.');
            }

            $factor = (float) $equivalencia->peq_factor;
            $peqId  = $equivalencia->peq_id;
        }

        return [$factor, round($precioUnitario / $factor, 4), $peqId];
    }

    /**
     * Inserta el movimiento de Entrada, recalcula el CPP en 'stock' y
     * recalcula producto.prd_pvtmin/prd_pvtmay.
     */
    private function registrarEntrada(CompraDetalle $detalle, int $depositoId): void
    {
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

    private function recalcularPrecioVenta(int $productoId, float $costoPromedio): void
    {
        $producto = Producto::with('tipoIva')->find($productoId);

        if (!$producto) {
            return;
        }

        $this->priceService->aplicarPrecios($producto, $costoPromedio);
    }

    /**
     * Anula una compra confirmada: contraasiento de stock por linea y baja
     * de la fila correspondiente en el Libro de Compras.
     */
    public function anular(CompraCab $compra): CompraCab
{
    if ($compra->com_estfac === CompraCab::ESTADO_ANULADA) {
        throw new \DomainException('La compra ya está anulada.');
    }

    return DB::transaction(function () use ($compra) {
        $compra->load('detalle');

        // NUEVO: si ya se registró algun pago (total o parcial) sobre alguna
        // cuota de esta compra, no se puede anular sin dejar un pago_compra
        // huerfano apuntando a una cuota que ya no existiria.
       // AHORA — apunta a la relacion 'pago' (solo el ACTIVO; un pago anulado no bloquea)
        $tienePagos = CuotaCompra::where('cuo_comid', $compra->com_id)
            ->whereHas('pago')
            ->exists();

        if ($tienePagos) {
            throw new \DomainException('No se puede anular: ya existen pagos registrados para esta compra.');
        }

        // Sin pagos, las cuotas pendientes quedan huerfanas de una compra
        // anulada: se eliminan (el FK cuo_comid es cascade, pero se borran
        // explicitamente aca para que quede claro en el codigo).
        CuotaCompra::where('cuo_comid', $compra->com_id)->delete();

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

        LibroCompra::where('lcm_comid', $compra->com_id)
            ->update(['lcm_estado' => LibroCompra::ESTADO_ANULADO]);

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

        $stock = Stock::where('sto_prdid', $detalle->cmd_prdid)
                      ->where('sto_depid', $depositoId)
                      ->lockForUpdate()
                      ->firstOrFail();

        $cantidadARevertir = (float) $detalle->cmd_canrec;

        if ((float) $stock->sto_cantid < $cantidadARevertir) {
            throw new \DomainException(
                "No se puede anular: el producto \"{$detalle->cmd_descri}\" no tiene stock suficiente " .
                "({$stock->sto_cantid} disponible, se necesitan {$cantidadARevertir})."
            );
        }

        $nuevaCantidad = (float) $stock->sto_cantid - $cantidadARevertir;

        $stock->sto_cantid = $nuevaCantidad;
        $stock->sto_actual = now();
        $stock->save();

        MovimientoStock::create([
            'mst_cmdid'  => $detalle->cmd_id,
            'mst_usuid'  => Auth::id(),
            'mst_revid'  => $entrada->mst_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $cantidadARevertir,
            'mst_cosuni' => $stock->sto_cosprm,
            'mst_salcan' => $nuevaCantidad,
            'mst_salcos' => round($nuevaCantidad * $stock->sto_cosprm, 4),
            'mst_obs'    => 'Reversa por anulación de compra',
            'mst_stprid' => $detalle->cmd_prdid,
            'mst_stdeid' => $depositoId,
        ]);
    }

        /**
     * Si la compra es a credito, genera N filas en cuota_compra a partir
     * de com_fchaem + plazo + intervalo, repartiendo com_total en partes
     * iguales. La ultima cuota absorbe el resto por redondeo, para que la
     * suma cierre exacto con com_total (mismo criterio que describe el
     * documento de referencia del usuario).
     */
    private function generarCuotas(CompraCab $compra): void
    {
        if ((int) $compra->com_condic !== CompraCab::CONDICION_CREDITO) {
            return;
        }

        $cantidadCuotas = (int) $compra->com_cuotas;
        $plazoDias      = (int) ($compra->com_plzdia ?? 0);
        $intervaloDias  = (int) ($compra->com_interv ?? 0);
        $total          = (float) $compra->com_total;

        $importeBase       = round($total / $cantidadCuotas, 2);
        $importeAcumulado  = 0.0;
        $ultimoVencimiento = null;

        for ($n = 1; $n <= $cantidadCuotas; $n++) {
            $importe = $n < $cantidadCuotas
                ? $importeBase
                : round($total - $importeAcumulado, 2); // ajuste de Gs. 1 en la ultima cuota

            $vencimiento = (clone $compra->com_fchaem)
                ->addDays($plazoDias + ($intervaloDias * ($n - 1)));

            CuotaCompra::create([
                'cuo_comid'   => $compra->com_id,
                'cuo_nrocuo'  => $n,
                'cuo_venci'   => $vencimiento,
                'cuo_importe' => $importe,
                'cuo_estado'  => CuotaCompra::ESTADO_PENDIENTE,
            ]);

            $importeAcumulado  += $importe;
            $ultimoVencimiento  = $vencimiento;
        }

        // com_fchvto queda como snapshot del vencimiento de la ULTIMA cuota,
        // util para reportes de cuentas por pagar sin tener que hacer join.
        $compra->com_fchvto = $ultimoVencimiento;
        $compra->saveQuietly();
    }

    /**
     * Marca una cuota como pagada. No hay reversa de pago: para corregir un
     * error se anula la compra completa (bloqueado si ya hay cuotas pagadas,
     * ver anular()).
     */
        /**
     * Registra un pago sobre una cuota. Soporta pago parcial: la cuota solo
     * pasa a Pagada cuando la suma de sus pagos cubre cuo_importe. El pago
     * en si nunca se borra ni se edita — es un registro contable.
     */
        /**
     * Un pago cubre 1 a N cuotas del MISMO proveedor (incluso de facturas
     * distintas). Ya viene validado por PagoCompraRequest: saldo suficiente,
     * cuotas del proveedor correcto, no repetidas.
     */
       /**
     * Registra el pago de UNA cuota (Opcion B: sin caja, sin pago
     * parcial). El pago no se edita ni se borra; para corregir un
     * error se usa anularPago().
     */
    public function registrarPago(array $datos): PagoCompra
    {
        return DB::transaction(function () use ($datos) {
            $cuota = CuotaCompra::lockForUpdate()->findOrFail($datos['cuo_id']);

            if ($cuota->cuo_estado === CuotaCompra::ESTADO_PAGADA) {
                throw new \DomainException('Esta cuota ya está pagada.');
            }

            $pago = PagoCompra::create([
                'pgo_cuoid'      => $cuota->cuo_id,
                'pgo_fecha'      => $datos['pgo_fecha'],
                'pgo_importe'    => $cuota->cuo_importe,
                'pgo_formapa'    => $datos['pgo_formapa'],
                'pgo_referencia' => $datos['pgo_referencia'] ?? null,
                'pgo_observ'     => $datos['pgo_observ'] ?? null,
                'pgo_estado'     => PagoCompra::ESTADO_ACTIVO,
                'pgo_usuid'      => Auth::id(),
            ]);

            $cuota->cuo_estado  = CuotaCompra::ESTADO_PAGADA;
            $cuota->cuo_fecpago = $datos['pgo_fecha'];
            $cuota->cuo_pagusu  = Auth::id();
            $cuota->save();

            return $pago->load('cuota.compraCab');
        });
    }

    /**
     * Anula un pago: la cuota vuelve a Pendiente. El pago no se borra
     * (trazabilidad); queda marcado pgo_estado = Anulado.
     */
    public function anularPago(PagoCompra $pago, string $motivo): PagoCompra
    {
        if ($pago->pgo_estado === PagoCompra::ESTADO_ANULADO) {
            throw new \DomainException('El pago ya está anulado.');
        }

        return DB::transaction(function () use ($pago, $motivo) {
            $pago->pgo_estado = PagoCompra::ESTADO_ANULADO;
            $pago->pgo_fecanu = now();
            $pago->pgo_anusid = Auth::id();
            $pago->pgo_motanu = $motivo;
            $pago->save();

            $cuota = CuotaCompra::lockForUpdate()->findOrFail($pago->pgo_cuoid);
            $cuota->cuo_estado  = CuotaCompra::ESTADO_PENDIENTE;
            $cuota->cuo_fecpago = null;
            $cuota->cuo_pagusu  = null;
            $cuota->save();

            return $pago->load('cuota.compraCab');
        });
    }

}