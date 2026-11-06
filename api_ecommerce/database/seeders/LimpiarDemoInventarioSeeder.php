<?php
// database/seeders/LimpiarDemoInventarioSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Elimina todo lo generado por DemoDemandaInventarioSeeder.
 *   php artisan db:seed --class=LimpiarDemoInventarioSeeder
 */
class LimpiarDemoInventarioSeeder extends Seeder
{
    public function run(): void
    {
        $marca = DemoDemandaInventarioSeeder::MARCA;

        DB::transaction(function () use ($marca) {
            $ventas = DB::table('venta_cab')->where('ven_obs', $marca)->pluck('ven_id');

            foreach ($ventas->chunk(1000) as $lote) {
                DB::table('venta_detalle')->whereIn('vnd_venid', $lote)->delete();
            }
            $nVentas = 0;
            foreach ($ventas->chunk(1000) as $lote) {
                $nVentas += DB::table('venta_cab')->whereIn('ven_id', $lote)->delete();
            }

            DB::table('timbrado')->where('tim_observacion', $marca)->delete();

            $this->command?->info("Facturas demo de inventario eliminadas: {$nVentas}");
        });
    }
}