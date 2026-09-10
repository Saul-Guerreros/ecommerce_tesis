<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoComprobanteSeeder extends Seeder
{
    /**
     * Valores de prueba confirmados por el usuario. tco_ambito y tco_afecstk
     * son criterios razonables para pruebas, no un catalogo oficial de SET:
     * V = documentos de venta, C = documentos de compra (Autofactura).
     */
    public function run(): void
    {
        $tipos = [
            ['tco_codigo' => '001', 'tco_descri' => 'Factura',         'tco_ambito' => 'V', 'tco_reqtim' => 1, 'tco_afecstk' => -1],
            ['tco_codigo' => '002', 'tco_descri' => 'Nota de Crédito', 'tco_ambito' => 'V', 'tco_reqtim' => 1, 'tco_afecstk' => 1],
            ['tco_codigo' => '003', 'tco_descri' => 'Nota de Débito',  'tco_ambito' => 'V', 'tco_reqtim' => 1, 'tco_afecstk' => 0],
            ['tco_codigo' => '004', 'tco_descri' => 'Remisión',        'tco_ambito' => 'V', 'tco_reqtim' => 1, 'tco_afecstk' => 0],
            ['tco_codigo' => '005', 'tco_descri' => 'Autofactura',     'tco_ambito' => 'C', 'tco_reqtim' => 1, 'tco_afecstk' => 1],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_comprobante')->updateOrInsert(
                ['tco_codigo' => $tipo['tco_codigo']],
                $tipo + ['tco_estado' => 1]
            );
        }
    }
}