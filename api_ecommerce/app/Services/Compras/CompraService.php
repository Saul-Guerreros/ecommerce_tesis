<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Compras\{CompraCab, CompraDetalle, Stock, MovimientoStock};
use App\Services\Precio\PriceService;
use Illuminate\Support\Facades\{DB, Auth};

class CompraService
{
    public function __construct(private readonly PriceService $priceService)
    {
    }

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
                [$factor, $costoUnitStock] = $this->resolverConversion(
                    $linea['prd_id'], $linea['umd_id'], (float) $linea['precun']
                );

                $detalle = CompraDetalle::create([
                    'cmd_comid'    => $compra->com_id,
                    'cmd_prdid'    => $linea['prd_id'],
                    'cmd_umdid'    => $linea['umd_id'],
                    'cmd_factconv' => $factor,
                    'cmd_tivid'    => $linea['tiv_id'],
                    'cmd_nroprd'   => $orden + 1,
                    'cmd_descri'   => $linea['descri'],
                    'cmd_precun'   => $linea['precun'],
                    'cmd_costo'    => $costoUnitStock,               // <- lo que ya consume registrarEntrada()
                    'cmd_despor'   => $linea['despor']  ?? 0,
                    'cmd_descto'   => $linea['descto']  ?? 0,
                    'cmd_tasa'     => $linea['tasa']    ?? 0,
                    'cmd_gravada'  => $linea['gravada'] ?? 0,
                    'cmd_iva'      => $linea['iva']     ?? 0,
                    'cmd_canfac'   => round($linea['canfac'] * $factor, 4), // convertido a unidad de STOCK
                    'cmd_canrec'   => round($linea['canrec'] * $factor, 4), // idem
                ]);

                if ((float) $detalle->cmd_canrec > 0) {
                    $this->registrarEntrada($detalle, $datosCab['com_depid']); // <- SIN CAMBIOS
                }
            }

            return $compra->load('detalle');
        });
    }

    /**
     * Resuelve el factor producto-unidad y el costo por unidad de STOCK a
     * partir del precio unitario facturado en la unidad elegida. Si la
     * unidad elegida es la unidad de stock del producto, el factor es 1 (no
     * necesita fila en producto_equivalencia).
     */
    private function resolverConversion(int $productoId, int $unidadId, float $precioUnitario): array
    {
        $producto = Producto::findOrFail($productoId);

        if ($unidadId === (int) $producto->prd_umdid) {
            $factor = 1.0;
        } else {
            $equivalencia = \App\Models\Compras\ProductoEquivalencia::where('peq_prdid', $productoId)
                ->where('peq_umdid', $unidadId)
                ->where('peq_estado', 1)
                ->first();

            // CompraRequest ya valido que exista; esto es una segunda barrera
            // por si algo cambio entre la validacion y el guardado.
            if (!$equivalencia) {
                throw new \DomainException('No existe una equivalencia activa para la unidad seleccionada.');
            }

            $factor = (float) $equivalencia->peq_factor;
        }

        return [$factor, round($precioUnitario / $factor, 4)];
    }

    /**
     * Inserta el movimiento de Entrada, recalcula el CPP en 'stock' y
     * recalcula producto.prd_pvtmin/prd_pvtmay. Se llama una vez por
     * linea con cantidad recibida > 0.
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
     * Delega en PriceService el cálculo de prd_pvtmin/prd_pvtmay a partir
     * del nuevo costo promedio. Ya no se corta si el producto no tiene
     * margen individual: siempre hay al menos el margen general
     * (ConfiguracionPrecio), así que el precio SIEMPRE se puede calcular.
     */
    private function recalcularPrecioVenta(int $productoId, float $costoPromedio): void
    {
        $producto = Producto::with('tipoIva')->find($productoId);

        if (!$producto) {
            return;
        }

        $this->priceService->aplicarPrecios($producto, $costoPromedio);
    }

    /**
     * Anula una compra confirmada. Por cada linea, genera un contraasiento
     * (salida a fecha actual) que resta del stock ACTUAL la cantidad que
     * esta compra habia ingresado. Se bloquea linea por linea solo si el
     * stock disponible ya no alcanza (es decir, parte de esa mercaderia
     * ya fue vendida/consumida), con un mensaje indicando que producto lo
     * impide.
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

    /**
     * Anula el efecto en stock de una entrada por compra generando un
     * CONTRAASIENTO hacia adelante (fecha actual), en vez de reconstruir
     * el estado historico. El Kardex nunca se reescribe: se agrega un
     * movimiento de salida nuevo, igual que se haria con una nota de
     * credito sobre una factura de venta.
     *
     * Se bloquea solo si el stock ACTUAL no alcanza para restar la
     * cantidad que esta compra habia ingresado (es decir, si parte de
     * esa mercaderia ya fue consumida por ventas, otros ajustes, etc.).
     * No importa si hubo movimientos posteriores; importa si fisicamente
     * queda suficiente cantidad para devolver.
     */
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

        // El CPP (costo promedio ponderado) no se recalcula al retirar
        // unidades al costo promedio actual: matematicamente, restar
        // cantidad no altera el promedio de lo que queda. Solo cambia
        // sto_cantid.
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

        // No se llama recalcularPrecioVenta(): sto_cosprm no cambio.
    }
}