<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Trabajos base de ejemplo. Los precios se ajustan después desde el panel.
 * Depende de TipoIvaSeeder (busca la tasa 10%).
 */
class TrabajoSeeder extends Seeder
{
    public function run(): void
    {
        $iva10 = DB::table('tipo_iva')->where('tiv_tasa', 10)->value('tiv_id');

        if (!$iva10) {
            $this->command?->warn('TrabajoSeeder: no existe tipo_iva 10%. Corré TipoIvaSeeder primero.');
            return;
        }

        $trabajos = [
            ['trb_codigo' => 'REVISION',    'trb_descri' => 'Revisión y diagnóstico',                     'trb_precio' => 50000,  'trb_dias' => 1],
            ['trb_codigo' => 'FORMATEO',    'trb_descri' => 'Formateo e instalación de sistema',          'trb_precio' => 120000, 'trb_dias' => 1],
            ['trb_codigo' => 'LIMPIEZA',    'trb_descri' => 'Limpieza interna y cambio de pasta térmica', 'trb_precio' => 100000, 'trb_dias' => 1],
            ['trb_codigo' => 'CAMB_PANTAL', 'trb_descri' => 'Cambio de pantalla (mano de obra)',          'trb_precio' => 80000,  'trb_dias' => 3],
            ['trb_codigo' => 'CAMB_TECLAD', 'trb_descri' => 'Cambio de teclado (mano de obra)',           'trb_precio' => 70000,  'trb_dias' => 3],
            ['trb_codigo' => 'CAMB_DISCO',  'trb_descri' => 'Cambio de disco y migración de datos',       'trb_precio' => 90000,  'trb_dias' => 2],
            ['trb_codigo' => 'REP_PLACA',   'trb_descri' => 'Reparación de placa madre',                  'trb_precio' => 250000, 'trb_dias' => 5],
            ['trb_codigo' => 'ELIM_VIRUS',  'trb_descri' => 'Eliminación de malware',                     'trb_precio' => 80000,  'trb_dias' => 1],
        ];

        foreach ($trabajos as $t) {
            DB::table('trabajo')->updateOrInsert(
                ['trb_codigo' => $t['trb_codigo']],
                [
                    'trb_descri' => $t['trb_descri'],
                    'trb_precio' => $t['trb_precio'],
                    'trb_dias'   => $t['trb_dias'],
                    'trb_tivid'  => $iva10,
                    'trb_estado' => 1,
                    'trb_creado' => now(),
                ],
            );
        }
    }
}
