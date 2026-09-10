<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoClienteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'PERSONA FISICA',
            'PERSONA JURIDICA',
        ];

        foreach ($tipos as $descri) {
            DB::table('tipo_cliente')->updateOrInsert(
                ['tpc_descri' => $descri],
                [
                    'tpc_estado' => 1,
                    'tpc_creado' => now(),
                ]
            );
        }
    }
}