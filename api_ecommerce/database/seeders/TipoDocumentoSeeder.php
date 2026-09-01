<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['tpd_codigo' => 'CI',  'tpd_descri' => 'CEDULA DE IDENTIDAD'],
            ['tpd_codigo' => 'RUC', 'tpd_descri' => 'RUC'],
            ['tpd_codigo' => 'PAS', 'tpd_descri' => 'PASAPORTE'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_documento')->updateOrInsert(
                ['tpd_codigo' => $tipo['tpd_codigo']],
                [
                    'tpd_descri' => $tipo['tpd_descri'],
                    'tpd_estado' => 1,
                    'tpd_creado' => now(),
                ]
            );
        }
    }
}