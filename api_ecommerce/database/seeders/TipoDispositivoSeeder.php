<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoDispositivoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['tdi_codigo' => 'NOTEBOOK',   'tdi_descri' => 'Notebook / Laptop'],
            ['tdi_codigo' => 'PC',         'tdi_descri' => 'PC de escritorio'],
            ['tdi_codigo' => 'ALLINONE',   'tdi_descri' => 'All in One'],
            ['tdi_codigo' => 'IMPRESORA',  'tdi_descri' => 'Impresora'],
            ['tdi_codigo' => 'MONITOR',    'tdi_descri' => 'Monitor'],
            ['tdi_codigo' => 'TABLET',     'tdi_descri' => 'Tablet'],
            ['tdi_codigo' => 'OTRO',       'tdi_descri' => 'Otro equipo'],
        ];

        foreach ($tipos as $t) {
            DB::table('tipo_dispositivo')->updateOrInsert(
                ['tdi_codigo' => $t['tdi_codigo']],
                ['tdi_descri' => $t['tdi_descri'], 'tdi_estado' => 1, 'tdi_creado' => now()],
            );
        }
    }
}
