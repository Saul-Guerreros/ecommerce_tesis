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
 * Stock del pedido. El ciclo completo tiene tres momentos, y separarlos es
 * intencional:
 *
 *   1. Al CONFIRMAR EL PEDIDO (PedidoService, Paso 2 del checkout) se
 *      incrementa sto_reserv. La unidad queda apartada pero sigue contada
 *      en sto_cantid.
 *   2. Al CONFIRMAR EL PAGO (descontarDefinitivo) se descuenta sto_cantid
 *      y se libera sto_reserv, y recién ahí nace el movimiento de kardex.
 *   3. Si el pedido VENCE sin pago (liberarReserva) se devuelve sto_reserv
 *      sin tocar sto_cantid ni el kardex: nunca salió nada del depósito.
 *
 * Si se descontara todo en el paso 1, un pedido abandonado dejaría
 * mercadería bloqueada en el sistema. Si no se reservara nada, dos
 * clientes podrían comprar la misma unidad.
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

    /**
     * Devuelve la reserva de un pedido que no se va a cumplir: venció el
     * plazo de pago, o el administrador lo canceló antes de cobrarlo.
     *
     * NO escribe movimiento_stock y NO toca sto_cantid, a diferencia de
     * descontarDefinitivo(): la mercadería nunca salió del depósito, así
     * que no hay nada que asentar en el kardex. Registrar un movimiento acá
     * ensuciaría el costeo promedio y el libro de inventario con unidades
     * que nunca se movieron.
     *
     * Es idempotente por diseño: el max(0, ...) evita que una doble
     * ejecución (dos corridas del comando a la vez, un reintento manual)
     * deje la reserva en negativo.
     */
    public function liberarReserva(Pedido $pedido): void
    {
        $deposito = $this->resolverDeposito($pedido);

        if (!$deposito) {
            return; // sin depósito resoluble no hubo reserva que liberar
        }

        $pedido->loadMissing('detalles.producto');

        foreach ($pedido->detalles as $detalle) {
            $producto = $detalle->producto;

            if (!$producto || !$producto->prd_afestk) {
                continue;
            }

            $stock = Stock::where('sto_prdid', $producto->prd_id)
                ->where('sto_depid', $deposito->dpo_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                continue;
            }

            $stock->update([
                'sto_reserv' => max(0.0, (float) $stock->sto_reserv - (float) $detalle->dpd_cantid),
                'sto_actual' => now(),
            ]);
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