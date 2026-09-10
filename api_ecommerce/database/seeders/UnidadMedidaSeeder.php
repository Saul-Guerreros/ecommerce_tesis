<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['umd_codigo' => 'UNI', 'umd_nombre' => 'Unidad',     'umd_decimal' => 0],
            ['umd_codigo' => 'KG',  'umd_nombre' => 'Kilogramo',  'umd_decimal' => 1],
            ['umd_codigo' => 'LT',  'umd_nombre' => 'Litro',      'umd_decimal' => 1],
            ['umd_codigo' => 'CJ',  'umd_nombre' => 'Caja',       'umd_decimal' => 0],
            ['umd_codigo' => 'DOC', 'umd_nombre' => 'Docena',     'umd_decimal' => 0],
            ['umd_codigo' => 'MT',  'umd_nombre' => 'Metro',      'umd_decimal' => 1],
        ];

        foreach ($unidades as $unidad) {
            DB::table('unidad_medida')->updateOrInsert(
                ['umd_codigo' => $unidad['umd_codigo']],
                $unidad + ['umd_estado' => 1]
            );
        }
    }
}