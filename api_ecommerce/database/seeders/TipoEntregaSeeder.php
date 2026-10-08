<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoEntregaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['ten_codigo' => 'RETIRO', 'ten_descri' => 'RETIRO EN SUCURSAL'],
            ['ten_codigo' => 'ENVIO',  'ten_descri' => 'ENVIO A DOMICILIO'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipo_entrega')->updateOrInsert(
                ['ten_codigo' => $tipo['ten_codigo']],
                [
                    'ten_descri' => $tipo['ten_descri'],
                    'ten_estado' => 1,
                    'ten_creado' => now(),
                ]
            );
        }
    }
}