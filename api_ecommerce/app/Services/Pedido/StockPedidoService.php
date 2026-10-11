<?php

namespace App\Services\Pedido;

use App\Exceptions\StockInsuficienteException;
use App\Models\Compras\MovimientoStock;
use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\Pedido;
use App\Models\Usuario;

/**
 * Descuento definitivo de stock del pedido.
 *
 * El ciclo completo tiene dos momentos, y separarlos es intencional:
 *
 *   1. Al CONFIRMAR EL PEDIDO (PedidoService) se incrementa sto_reserv.
 *      La unidad queda apartada pero sigue contada en sto_cantid.
 *   2. Al CONFIRMAR EL PAGO (este service) se descuenta sto_cantid y se
 *      libera sto_reserv, y recien ahi nace el movimiento de kardex.
 *
 * Si se descontara todo en el paso 1, un pedido abandonado dejaria
 * mercaderia bloqueada en el sistema. Si no se reservara nada, dos
 * clientes podrian comprar la misma unidad.
 */
class StockPedidoService
{
    /**
     * @throws StockInsuficienteException si alguna linea ya no tiene stock
     */
    public function descontarDefinitivo(Pedido $pedido, ?Usuario $usuario = null): void
    {
        $deposito = $this->resolverDeposito($pedido);

        $pedido->loadMissing('detalles.producto');

        $faltantes = [];
        $aplicar   = [];

        // Primera pasada: se bloquean y se validan TODAS las filas antes de
        // tocar ninguna. Confirmar medio pedido y fallar en la ultima linea
        // dejaria el inventario inconsistente, aunque la transaccion revierta
        // despues: es mas claro no empezar.
        foreach ($pedido->detalles as $detalle) {
            $producto = $detalle->producto;

            if (!$producto || !$producto->prd_afestk) {
                continue; // servicio o producto que no controla stock
            }

            abort_if(!$deposito, 422, 'No se pudo determinar el depósito de despacho del pedido.');

            $stock = Stock::where('sto_prdid', $producto->prd_id)
                ->where('sto_depid', $deposito->dpo_id)
                ->lockForUpdate()
                ->first();

            $cantidad   = (float) $detalle->dpd_cantid;
            $disponible = $stock ? (float) $stock->sto_cantid : 0.0;

            if ($disponible < $cantidad) {
                $faltantes[] = [
                    'producto'   => $detalle->dpd_descri ?: $producto->prd_descri,
                    'requiere'   => $cantidad,
                    'disponible' => $disponible,
                ];

                continue;
            }

            $aplicar[] = ['stock' => $stock, 'detalle' => $detalle, 'cantidad' => $cantidad];
        }

        if (!empty($faltantes)) {
            throw new StockInsuficienteException($faltantes);
        }

        // Segunda pasada: aplicar.
        foreach ($aplicar as $item) {
            $this->aplicarSalida($item['stock'], $item['detalle'], $item['cantidad'], $deposito, $usuario);
        }
    }

    private function aplicarSalida(
        Stock $stock,
        $detalle,
        float $cantidad,
        Deposito $deposito,
        ?Usuario $usuario,
    ): void {
        $saldoCantidad = (float) $stock->sto_cantid - $cantidad;

        // La reserva se libera junto con el descuento: la unidad deja de
        // estar "apartada" porque ya salio del deposito. max(0, ...) protege
        // de datos heredados de pedidos anteriores a la reserva.
        $saldoReserva = max(0.0, (float) $stock->sto_reserv - $cantidad);

        $stock->update([
            'sto_cantid' => $saldoCantidad,
            'sto_reserv' => $saldoReserva,
            'sto_actual' => now(),
        ]);

        $costoUnitario = (float) $stock->sto_cosprm;

        MovimientoStock::create([
            'mst_usuid'  => $usuario?->usu_id,
            'mst_dpdid'  => $detalle->dpd_id,
            'mst_fecha'  => now(),
            'mst_tipo'   => 'S',
            'mst_cantid' => $cantidad,
            'mst_cosuni' => $costoUnitario,
            'mst_salcan' => $saldoCantidad,
            'mst_salcos' => $costoUnitario, // costeo promedio ponderado: la salida no lo altera
            'mst_obs'    => 'Venta online ' . $detalle->pedido?->ped_numero,
            'mst_stprid' => $detalle->dpd_prdid,
            'mst_stdeid' => $deposito->dpo_id,
        ]);
    }

    /**
     * Mismo criterio que PedidoService::resolverDeposito(): el principal de
     * la sucursal de retiro, o el de la sucursal principal si es envio.
     * Tiene que coincidir con el deposito donde se hizo la reserva, de lo
     * contrario se liberaria una reserva de un deposito y se descontaria
     * de otro.
     */
    private function resolverDeposito(Pedido $pedido): ?Deposito
    {
        $sucursalId = $pedido->ped_sucid ?: Sucursal::where('suc_princi', 1)->value('suc_id');

        if (!$sucursalId) {
            return null;
        }

        return Deposito::where('dpo_sucid', $sucursalId)
            ->where('dpo_princi', 1)
            ->first();
    }
}