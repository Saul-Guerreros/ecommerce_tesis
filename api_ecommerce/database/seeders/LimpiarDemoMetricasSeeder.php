<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Elimina SOLO los datos generados por DemoMetricasSeeder (identificados por
 * sus marcas). No toca pedidos, ventas, compras ni clientes reales.
 *
 * Uso:
 *   php artisan db:seed --class=LimpiarDemoMetricasSeeder
 */
class LimpiarDemoMetricasSeeder extends Seeder
{
    public function run(): void
    {
        $marca = DemoMetricasSeeder::MARCA;

        DB::transaction(function () use ($marca) {
            // --- Ventas (antes que pedidos: venta_cab referencia al pedido) ---
            $ventas = DB::table('venta_cab')->where('ven_obs', $marca)->pluck('ven_id');
            DB::table('venta_detalle')->whereIn('vnd_venid', $ventas)->delete();
            $nVentas = DB::table('venta_cab')->whereIn('ven_id', $ventas)->delete();

            // --- Pedidos ---
            $pedidos = DB::table('pedido')->where('ped_numero', 'like', 'DEMO-%')->pluck('ped_id');
            DB::table('pedido_pago')->whereIn('ppa_pedid', $pedidos)->delete();
            DB::table('pedido_entrega')->whereIn('pen_pedid', $pedidos)->delete();
            DB::table('pedido_historial_estado')->whereIn('peh_pedid', $pedidos)->delete();
            DB::table('detalle_pedido')->whereIn('dpd_pedid', $pedidos)->delete();
            $nPedidos = DB::table('pedido')->whereIn('ped_id', $pedidos)->delete();

            // --- Compras ---
            $compras = DB::table('compra_cab')->where('com_obs', $marca)->pluck('com_id');
            $cuotas = DB::table('cuota_compra')->whereIn('cuo_comid', $compras)->pluck('cuo_id');
            DB::table('pago_compra')->whereIn('pgo_cuoid', $cuotas)->delete();
            DB::table('cuota_compra')->whereIn('cuo_id', $cuotas)->delete();
            $nCompras = DB::table('compra_cab')->whereIn('com_id', $compras)->delete();

            // --- Chatbot ---
            $conversaciones = DB::table('chat_conversacion')->where('cnv_ip', $marca)->pluck('cnv_id');
            DB::table('chat_mensaje')->whereIn('cnv_id', $conversaciones)->delete();
            $nChat = DB::table('chat_conversacion')->whereIn('cnv_id', $conversaciones)->delete();

            // --- Clientes ---
            $personas = DB::table('persona')->where('per_cedula', 'like', 'DEMO-%')->pluck('per_id');
            DB::table('contacto')->whereIn('con_perid', $personas)->delete();
            $nClientes = DB::table('cliente')->whereIn('cli_perid', $personas)->delete();
            DB::table('persona')->whereIn('per_id', $personas)->delete();

            // --- Timbrados demo (las facturas que los usaban ya se borraron) ---
            DB::table('timbrado')->where('tim_observacion', $marca)->delete();

            $this->command->info('Datos demo eliminados:');
            $this->command->table(['Entidad', 'Eliminados'], [
                ['Facturas de venta', $nVentas],
                ['Pedidos', $nPedidos],
                ['Compras', $nCompras],
                ['Conversaciones', $nChat],
                ['Clientes', $nClientes],
            ]);
        });
    }
}
