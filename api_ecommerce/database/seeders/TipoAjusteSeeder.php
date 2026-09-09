<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoAjusteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['tpa_codig' => 'SOB', 'tpa_descri' => 'Sobrante de inventario', 'tpa_natura' => 'P', 'tpa_reqcom' => 0],
            ['tpa_codig' => 'MER', 'tpa_descri' => 'Merma',                  'tpa_natura' => 'N', 'tpa_reqcom' => 1],
            ['tpa_codig' => 'ROT', 'tpa_descri' => 'Rotura / daño',          'tpa_natura' => 'N', 'tpa_reqcom' => 1],
            ['tpa_codig' => 'VEN', 'tpa_descri' => 'Vencimiento',            'tpa_natura' => 'N', 'tpa_reqcom' => 1],
            ['tpa_codig' => 'ROB', 'tpa_descri' => 'Robo / faltante',        'tpa_natura' => 'N', 'tpa_reqcom' => 1],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_ajuste')->updateOrInsert(
                ['tpa_codig' => $tipo['tpa_codig']],
                $tipo + ['tpa_estado' => 1]
            );
        }
    }
}