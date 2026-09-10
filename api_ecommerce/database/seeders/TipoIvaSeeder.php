<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Compras\TipoIva;

/**
 * Tasas de IVA vigentes en Paraguay (Ley 6380/2019).
 * Son valores fijos que la tienda no define: sin ellos no se puede dar de
 * alta un producto ni desglosar una factura.
 *
 * Idempotente: updateOrCreate sobre la tasa permite re-ejecutar el seeder
 * sin duplicar ni chocar con uq_tiv_tasa.
 */
class TipoIvaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['tiv_tasa' => 0.00,  'tiv_descri' => 'Exenta'],
            ['tiv_tasa' => 5.00,  'tiv_descri' => 'IVA 5%'],
            ['tiv_tasa' => 10.00, 'tiv_descri' => 'IVA 10%'],
        ];

        foreach ($tipos as $tipo) {
            TipoIva::updateOrCreate(
                ['tiv_tasa' => $tipo['tiv_tasa']],
                ['tiv_descri' => $tipo['tiv_descri'], 'tiv_estado' => 1]
            );
        }
    }
}
