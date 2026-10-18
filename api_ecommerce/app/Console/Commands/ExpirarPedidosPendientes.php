<?php

namespace App\Console\Commands;

use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoHistorialEstado;
use App\Services\Pedido\StockPedidoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancela los pedidos que vencieron sin pago y devuelve su reserva de
 * stock.
 *
 * Es la contracara obligatoria del rediseño del checkout: desde que el
 * pedido se crea ANTES del pago, la reserva queda tomada por un cliente
 * que todavía no pagó. Si abandona el Paso 3, esa unidad queda bloqueada
 * para el resto de la tienda. Este comando es lo que evita que el arreglo
 * de un problema (sobreventa) cree otro (stock fantasma).
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

    protected $description = 'Cancela los pedidos vencidos sin pago y libera el stock reservado';

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
                . ' · Gs. ' . number_format((float) $pedido->ped_total, 0, ',', '.'));

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

        return self::SUCCESS;
    }

    /**
     * Candidatos: pendientes de pago, con vencimiento cumplido y todavía no
     * cancelados.
     *
     * whereNotNull(ped_vence) deja afuera a los pedidos del flujo anterior,
     * que nacían ya pagados y no tienen plazo.
     */
    private function pedidosVencidos(int $limite)
    {
        return Pedido::pendienteDePago()
            ->whereNotNull('ped_vence')
            ->where('ped_vence', '<', now())
            ->whereHas('estadoPedido', fn ($q) => $q->where('est_codigo', '!=', EstadoPedido::CANCELADO))
            ->with('detalles.producto', 'estadoPedido')
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

            if (!$actual || !$actual->pagable()) {
                return; // ya pagó, ya se canceló, o lo tomó otra corrida
            }

            $stock->liberarReserva($actual);

            $cancelado = EstadoPedido::porCodigo(EstadoPedido::CANCELADO);
            $anterior  = $actual->ped_estid;

            $actual->update([
                'ped_estid'  => $cancelado->est_id,
                'ped_obsint' => 'Cancelado automáticamente: venció el plazo de pago.',
            ]);

            PedidoHistorialEstado::create([
                'peh_pedid'  => $actual->ped_id,
                'peh_usuid'  => null,   // acción del sistema, no de un usuario
                'peh_tipo'   => 'PEDIDO',
                'peh_estant' => $anterior,
                'peh_estid'  => $cancelado->est_id,
                'peh_coment' => 'Vencido sin pago — stock liberado',
            ]);
        });
    }
}