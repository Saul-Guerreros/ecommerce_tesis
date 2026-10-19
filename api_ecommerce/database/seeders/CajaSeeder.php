<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea una caja por cada punto de expedicion que ya tiene timbrado
 * cargado. Sin esto, la primera factura falla: venta_cab.ven_cajid es
 * NOT NULL y el servicio resuelve la caja por (sucursal, punto de
 * expedicion del timbrado).
 *
 * Idempotente: se puede volver a correr sin duplicar.
 */
class CajaSeeder extends Seeder
{
    public function run(): void
    {
        $puntos = DB::table('timbrado')
            ->select('tim_sucid', 'tim_puntexp')
            ->distinct()
            ->get();

        foreach ($puntos as $punto) {
            DB::table('caja')->updateOrInsert(
                ['caj_sucid' => $punto->tim_sucid, 'caj_puntexp' => $punto->tim_puntexp],
                [
                    'caj_descri' => 'Tienda Web ' . $punto->tim_puntexp,
                    'caj_tipo'   => 2,   // virtual: la venta no pasa por un cajon de efectivo
                    'caj_estado' => 1,
                ]
            );
        }
    }
}