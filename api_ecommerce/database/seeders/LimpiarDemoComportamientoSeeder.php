<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Borra SOLO la actividad demo (visitantes con el prefijo VISITA_DEMO).
 *
 *   php artisan db:seed --class=LimpiarDemoComportamientoSeeder
 */
class LimpiarDemoComportamientoSeeder extends Seeder
{
    public function run(): void
    {
        $prefijo = DemoComportamientoSeeder::VISITA_DEMO . '%';

        $vistas = DB::table('vista_producto')->whereRaw('vpr_visita::text LIKE ?', [$prefijo])->delete();
        $busquedas = DB::table('busqueda')->whereRaw('bus_visita::text LIKE ?', [$prefijo])->delete();

        $this->command?->info("Demo de comportamiento eliminada: {$busquedas} búsqueda(s), {$vistas} vista(s).");
    }
}