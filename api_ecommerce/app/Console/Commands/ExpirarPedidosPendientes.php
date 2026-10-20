<?php

namespace App\Console\Commands;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Services\Pedido\StockPedidoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancela los pedidos que vencieron sin cobrarse y devuelve su reserva de
 * stock.
 *
 * Es la contracara obligatoria del rediseño del checkout: desde que el
 * pedido se crea ANTES del pago, la reserva queda tomada por un cliente
 * que todavía no pagó. Si abandona el Paso 3, esa unidad queda bloqueada
 * para el resto de la tienda. Este comando es lo que evita que el arreglo
 * de un problema (sobreventa) cree otro (stock fantasma).
 *
 * Cubre los dos finales posibles de una reserva sin cobrar: el checkout
 * abandonado (PENDIENTE_PAGO) y el comprobante rechazado que el cliente
 * no reemplazó dentro del plazo nuevo (RECHAZADO).
 *
 * Nunca cierra un pedido donde ya entró dinero: si hay algún pago
 * confirmado, o si el stock ya se descontó de verdad, lo deja para que lo
 * resuelva una persona.
 *
 *   php artisan pedidos:expirar
 *   php artisan pedidos:expirar --dry-run     (sólo informa, no toca nada)
 *   php artisan pedidos:expirar --limite=50
 */
class ExpirarPedidosPendientes extends Command
{
    protected $signature = 'pedidos:expirar
                            {--dry-run : Listar los pedidos que se cancelarían, sin cancelarlos}
                            {--limite=200 : Máximo de pedidos a procesar en esta corrida}';

    protected $description = 'Cancela los pedidos vencidos sin cobrar (sin pago o con comprobante rechazado) y libera el stock reservado';

    public function handle(StockPedidoService $stock): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $limite     = max(1, (int) $this->option('limite'));

        $pedidos = $this->pedidosVencidos($limite);

        if ($pedidos->isEmpty()) {
            $this->info('No hay pedidos vencidos.');

            return self::SUCCESS;
        }

        $this->info($pedidos->count() . ' pedido(s) vencido(s)' . ($simulacion ? ' [simulación]' : '') . ':');

        $cancelados = 0;

        foreach ($pedidos as $pedido) {
            $this->line("  {$pedido->ped_numero} · venció {$pedido->ped_vence->diffForHumans()}"
                . ' · Gs. ' . number_format((float) $pedido->ped_total, 0, ',', '.')
                . ' · ' . ($pedido->estadoPago?->epa_codigo === EstadoPago::RECHAZADO
                    ? 'comprobante rechazado sin reemplazo'
                    : 'sin pago declarado'));

            if ($simulacion) {
                continue;
            }

            try {
                $this->expirar($pedido, $stock);
                $cancelados++;
            } catch (\Throwable $e) {
                // Un pedido que falla no puede frenar al resto: cada uno va
                // en su propia transacción.
                $this->error("    No se pudo cancelar {$pedido->ped_numero}: {$e->getMessage()}");
                Log::error('pedidos:expirar falló', [
                    'ped_id' => $pedido->ped_id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if (!$simulacion) {
            $this->info("Listo: {$cancelados} pedido(s) cancelado(s) y stock liberado.");
        }

        $this->barrerRetirosVencidos($stock, $limite, $simulacion);

        return self::SUCCESS;
    }

    /**
     * Segunda pasada: pedidos LISTO cuyo plazo de retiro en sucursal
     * venció.
     *
     * Va en el mismo comando y no en uno nuevo porque comparte el
     * disparador (una corrida periódica) y el efecto (liberar reservas
     * que quedaron tomadas). Lo que NO comparte es el criterio, y esa
     * diferencia es el punto central de esta función:
     *
     *   - Contra entrega sin pagar nada -> cancelar y liberar stock,
     *     igual que una expiración de pago. No hay plata de por medio.
     *   - Ya pagó -> NO cancelar. Queda en LISTO con el ped_vencret
     *     vencido y la bandeja lo muestra en rojo para que el admin lo
     *     resuelva.
     *
     * El motivo de esa segunda regla no es de código sino de negocio: si
     * el sistema cancelara solo un pedido ya pagado, la tienda se
     * quedaría con el dinero del cliente Y con la mercadería. Eso
     * necesita que una persona llame al cliente y decida si extiende el
     * plazo, lo manda a domicilio o le devuelve la plata. Ninguna de esas
     * tres salidas la puede elegir una tarea automática.
     */
    private function barrerRetirosVencidos(StockPedidoService $stock, int $limite, bool $simulacion): void
    {
        $pedidos = $this->retirosVencidos($limite);

        if ($pedidos->isEmpty()) {
            $this->info('No hay retiros vencidos.');

            return;
        }

        $this->info($pedidos->count() . ' retiro(s) vencido(s)' . ($simulacion ? ' [simulación]' : '') . ':');

        $cancelados = 0;
        $revisar    = 0;

        foreach ($pedidos as $pedido) {
            $pagado = $pedido->tienePagoConfirmado();

            $this->line("  {$pedido->ped_numero} · venció {$pedido->ped_vencret->diffForHumans()}"
                . ' · ' . ($pagado
                    ? 'YA PAGADO → queda para revisión del admin'
                    : 'sin pago → se cancela'));

            if ($pagado) {
                // Se deja tal cual, a propósito: el dato de que está
                // vencido ya está en ped_vencret y la bandeja lo pinta
                // de rojo. No hace falta un estado ni una bandera nueva.
                $revisar++;
                continue;
            }

            if ($simulacion) {
                continue;
            }

            try {
                $this->expirarRetiro($pedido, $stock);
                $cancelados++;
            } catch (\Throwable $e) {
                $this->error("    No se pudo cancelar {$pedido->ped_numero}: {$e->getMessage()}");
                Log::error('pedidos:expirar (retiro) falló', [
                    'ped_id' => $pedido->ped_id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if (!$simulacion) {
            $this->info("Listo: {$cancelados} retiro(s) cancelado(s), {$revisar} para revisión del admin.");
        }
    }

    /**
     * Pedidos en LISTO con el plazo de retiro cumplido.
     *
     * No se filtra por pago acá: los ya pagados también tienen que
     * aparecer, porque son los que hay que contar como "para revisión".
     * La distinción se hace al procesarlos, no al buscarlos.
     */
    private function retirosVencidos(int $limite)
    {
        return Pedido::whereNotNull('ped_vencret')
            ->where('ped_vencret', '<', now())
            ->whereHas('estadoPedido', fn ($q) => $q->where('est_codigo', EstadoPedido::LISTO))
            ->with('detalles.producto', 'estadoPedido', 'estadoPago')
            ->orderBy('ped_vencret')
            ->limit($limite)
            ->get();
    }

    /**
     * Cancela un retiro vencido no pagado y libera su reserva.
     *
     * Mismo patrón que expirar(): lock de la fila y revalidación dentro
     * de la transacción, porque entre la consulta y el procesamiento el
     * cliente pudo haber pagado o el admin pudo haberlo entregado.
     */
    private function expirarRetiro(Pedido $pedido, StockPedidoService $stock): void
    {
        DB::transaction(function () use ($pedido, $stock) {
            $actual = Pedido::whereKey($pedido->ped_id)
                ->lockForUpdate()
                ->with('estadoPago', 'estadoPedido')
                ->first();

            if (
                !$actual
                || !$actual->retiroVencido()
                || $actual->estadoPedido?->est_codigo !== EstadoPedido::LISTO
                || $actual->tienePagoConfirmado()
            ) {
                return; // pagó, lo entregaron, o lo tomó otra corrida
            }

            if ($stock->yaDescontado($actual)) {
                return; // salió del depósito: no lo cierra el cron
            }

            $stock->liberarReserva($actual);

            $cancelado = EstadoPedido::porCodigo(EstadoPedido::CANCELADO);
            $anterior  = $actual->ped_estid;

            $actual->update([
                'ped_estid'   => $cancelado->est_id,
                'ped_vencret' => null,
                'ped_obsint'  => 'Cancelado automáticamente: venció el plazo de retiro en sucursal sin pago.',
            ]);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $actual->ped_id,
                'peh_usuid'  => null,
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => $anterior,
                'peh_estid'  => $cancelado->est_id,
                'peh_coment' => 'Retiro vencido sin pago — stock liberado',
            ]);
        });
    }

    /**
     * Candidatos: reserva viva sin cobrar, con vencimiento cumplido y
     * todavía no cancelados.
     *
     * Son dos casos, no uno (ver Pedido::ESTADOS_PAGO_EXPIRABLES):
     * el checkout abandonado en el Paso 3, y el pedido cuyo comprobante
     * fue rechazado y que no aprovechó el plazo nuevo para subir otro.
     * Antes sólo se miraba el primero, así que un rechazo dejaba la
     * reserva tomada de forma indefinida.
     *
     * whereNotNull(ped_vence) deja afuera a los pedidos del flujo anterior,
     * que nacían ya pagados y no tienen plazo.
     *
     * El filtro de pagos confirmados excluye los pedidos donde ya entró
     * dinero del cliente (pago dividido con una parte confirmada): esos
     * no los puede cerrar una tarea automática.
     */
    private function pedidosVencidos(int $limite)
    {
        return Pedido::conPagoExpirable()
            ->whereNotNull('ped_vence')
            ->where('ped_vence', '<', now())
            ->whereHas('estadoPedido', fn ($q) => $q->whereNotIn('est_codigo', [
                EstadoPedido::CANCELADO,
                EstadoPedido::ENTREGADO,
            ]))
            ->whereDoesntHave(
                'pagos.estadoPago',
                fn ($q) => $q->where('epa_codigo', EstadoPago::CONFIRMADO)
            )
            ->with('detalles.producto', 'estadoPedido', 'estadoPago')
            ->orderBy('ped_vence')
            ->limit($limite)
            ->get();
    }

    /**
     * Cada pedido se vuelve a leer con lock dentro de su transacción: entre
     * el listado y este momento el cliente pudo haber entrado a pagar. Sin
     * esta relectura se podría cancelar un pedido que acaba de recibir su
     * comprobante.
     */
    private function expirar(Pedido $pedido, StockPedidoService $stock): void
    {
        DB::transaction(function () use ($pedido, $stock) {
            $actual = Pedido::whereKey($pedido->ped_id)
                ->lockForUpdate()
                ->with('estadoPago', 'estadoPedido')
                ->first();

            // expirable() reemplaza a pagable(): este barrido ya no cubre
            // sólo el checkout abandonado, y pagable() responde otra
            // pregunta (si el cliente puede entrar al Paso 3).
            if (!$actual || !$actual->expirable()) {
                return; // ya pagó, ya se canceló, o lo tomó otra corrida
            }

            // Cinturón y tirantes: si por algún camino este pedido llegó a
            // descontar stock real, liberar la reserva dejaría el
            // inventario inflado. No lo cierra el cron.
            if ($stock->yaDescontado($actual)) {
                return;
            }

            $stock->liberarReserva($actual);

            $cancelado = EstadoPedido::porCodigo(EstadoPedido::CANCELADO);
            $anterior  = $actual->ped_estid;

            $rechazado = $actual->estadoPago?->epa_codigo === EstadoPago::RECHAZADO;

            $actual->update([
                'ped_estid'  => $cancelado->est_id,
                'ped_obsint' => $rechazado
                    ? 'Cancelado automáticamente: el cliente no reemplazó el comprobante rechazado dentro del plazo.'
                    : 'Cancelado automáticamente: venció el plazo de pago.',
            ]);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $actual->ped_id,
                'peh_usuid'  => null,   // acción del sistema, no de un usuario
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => $anterior,
                'peh_estid'  => $cancelado->est_id,
                'peh_coment' => $rechazado
                    ? 'Vencido sin comprobante de reemplazo — stock liberado'
                    : 'Vencido sin pago — stock liberado',
            ]);
        });
    }
}