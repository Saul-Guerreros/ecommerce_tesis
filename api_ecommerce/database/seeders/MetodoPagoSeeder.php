<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['mpa_codigo' => 'TRANSFERENCIA',   'mpa_descri' => 'Transferencia Bancaria',       'mpa_requcom' => true],
            ['mpa_codigo' => 'BILLETERA_MOVIL', 'mpa_descri' => 'Billetera Móvil (Tigo Money)', 'mpa_requcom' => true],
            ['mpa_codigo' => 'CONTRAENTREGA',   'mpa_descri' => 'Efectivo',                     'mpa_requcom' => false],

            // mpa_requcom = false: el cheque NO se adjunta como archivo.
            // Su "comprobante" son los datos declarados (banco, CI y
            // titular), que se guardan en pago_cheque. La verificación la
            // hace el admin cuando el cheque llega físicamente a la
            // sucursal.
            ['mpa_codigo' => 'CHEQUE',          'mpa_descri' => 'Cheque',                       'mpa_requcom' => false],
        ];

        foreach ($metodos as $m) {
            DB::table('metodo_pago')->updateOrInsert(
                ['mpa_codigo' => $m['mpa_codigo']],
                [...$m, 'mpa_estado' => true],
            );
        }
    }
}