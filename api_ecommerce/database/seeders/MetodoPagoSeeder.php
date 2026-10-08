<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['mpa_codigo' => 'TRANSFERENCIA',   'mpa_descri' => 'Transferencia Bancaria',      'mpa_requcom' => true],
            ['mpa_codigo' => 'BILLETERA_MOVIL', 'mpa_descri' => 'Billetera Móvil (Tigo Money)', 'mpa_requcom' => true],
            ['mpa_codigo' => 'CONTRAENTREGA',   'mpa_descri' => 'Efectivo',                     'mpa_requcom' => false],
        ];

        foreach ($metodos as $m) {
            DB::table('metodo_pago')->updateOrInsert(
                ['mpa_codigo' => $m['mpa_codigo']],
                [...$m, 'mpa_estado' => true],
            );
        }
    }
}