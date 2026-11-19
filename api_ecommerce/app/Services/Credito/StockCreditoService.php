<?php

namespace App\Services\Credito;

use App\Models\Compras\Stock;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Credito\SolicitudCredito;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Stock de la solicitud de credito. Espejo de StockPedidoService, pero
 * con solo dos momentos en vez de tres:
 *
 *   1. Al GENERAR la solicitud (reservar) se incrementa sto_reserv. La
 *      unidad queda apartada pero sigue contada en sto_cantid.
 *   2. Al RECHAZAR, ANULAR o VENCER la oferta (liberar) se devuelve
 *      sto_reserv sin tocar sto_cantid: nunca salio nada del deposito y
 *      el kardex no se entera, igual que liberarReserva() del pedido.
 *
 * NO existe el tercer momento (descontar definitivo): la solicitud nunca
 * descuenta stock. Cuando el cliente acepta y pasa al checkout, la
 * reserva se TRASPASA al pedido (etapa D) y de ahi en adelante manda
 * StockPedidoService.
 *
 * El credito se retira unicamente en casa matriz, asi que el deposito es
 * siempre el principal de la sucursal principal. Por eso resolverDeposito()
 * no recibe parametros, a diferencia del de PedidoService.
 */
class StockCreditoService
{
    /**
     * Aparta el stock de los items del carrito a credito.
     *
     * Se llama DENTRO de la transaccion de SolicitudService::generar().
     * lockForUpdate() pone en fila dos solicitudes simultaneas sobre la
     * ultima unidad, y la segunda falla antes de crear nada.
     *
     * Devuelve el deposito usado, que el llamador guarda en scr_depid.
     *
     * @param Collection $items lineas del carrito con car_modpag=credito
     * @throws ValidationException si alguna linea no tiene stock
     */
    public function reservar(Collection $items): ?Deposito
    {
        $deposito = $this->resolverDeposito();
        $reservo  = false;

        foreach ($items as $item) {
            $producto = $item->producto;

            if (! $producto || ! $producto->prd_afestk) {
                continue; // producto que no controla stock
            }

            abort_if(! $deposito, 422, 'No se pudo determinar el depósito de la casa matriz.');

            $stock = Stock::where('sto_prdid', $producto->prd_id)
                ->where('sto_depid', $deposito->dpo_id)
                ->lockForUpdate()
                ->first();

            $disponible = $stock ? ((float) $stock->sto_cantid - (float) $stock->sto_reserv) : 0.0;

            if ($disponible < (float) $item->car_cantid) {
                throw ValidationException::withMessages([
                    'stock' => "No hay stock suficiente de \"{$producto->prd_descri}\" para solicitar el crédito.",
                ]);
            }

            $stock->increment('sto_reserv', (float) $item->car_cantid);
            $stock->update(['sto_actual' => now()]);

            $reservo = true;
        }

        // Si ningun item controlaba stock no hubo reserva: devolver el
        // deposito igual dejaria scr_depid cargado y scr_fecres tambien,
        // y el barrido intentaria liberar algo que no existe.
        return $reservo ? $deposito : null;
    }

    /**
     * Devuelve las unidades apartadas y apaga la marca de reserva.
     *
     * Idempotente por dos vias: sale temprano si scr_fecres ya es NULL, y
     * el max(0, ...) evita dejar sto_reserv en negativo si dos corridas
     * del barrido se cruzan.
     *
     * Libera contra scr_depid, el deposito GUARDADO, nunca contra uno
     * recalculado: si cambiara la sucursal principal, recalcular
     * devolveria las unidades al deposito equivocado.
     */
    public function liberar(SolicitudCredito $solicitud): void
    {
        if (! $solicitud->tieneReserva() || ! $solicitud->scr_depid) {
            return;
        }

        $solicitud->loadMissing('detalles.producto');

        foreach ($solicitud->detalles as $detalle) {
            $producto = $detalle->producto;

            if (! $producto || ! $producto->prd_afestk) {
                continue;
            }

            $stock = Stock::where('sto_prdid', $producto->prd_id)
                ->where('sto_depid', $solicitud->scr_depid)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->update([
                'sto_reserv' => max(0.0, (float) $stock->sto_reserv - (float) $detalle->scd_cantid),
                'sto_actual' => now(),
            ]);
        }

        // Apagar la marca y NO el deposito: scr_depid queda como registro
        // de donde estuvo apartada la mercaderia.
        $solicitud->update(['scr_fecres' => null]);
    }

    /**
     * Casa matriz: deposito principal de la sucursal principal.
     *
     * El credito no ofrece elegir sucursal, asi que no hay nada que
     * resolver desde la entrega. El checkout de un pedido que venga de
     * credito tiene que respetar esto y aceptar solo retiro en casa
     * matriz (etapa D), o la reserva quedaria en un deposito distinto al
     * del pedido.
     */
    public function resolverDeposito(): ?Deposito
    {
        $sucursalId = Sucursal::where('suc_princi', 1)->value('suc_id');

        if (! $sucursalId) {
            return null;
        }

        return Deposito::where('dpo_sucid', $sucursalId)
            ->where('dpo_princi', 1)
            ->first();
    }
}