<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['epa_codigo' => 'PENDIENTE',  'epa_descri' => 'Pendiente de verificación'],
            ['epa_codigo' => 'CONFIRMADO', 'epa_descri' => 'Pago confirmado'],
            ['epa_codigo' => 'RECHAZADO',  'epa_descri' => 'Pago rechazado'],
            ['epa_codigo' => 'PARCIAL',    'epa_descri' => 'Pago parcial'],   // <-- nuevo
        ];

        foreach ($estados as $e) {
            DB::table('estado_pago')->updateOrInsert(
                ['epa_codigo' => $e['epa_codigo']],
                ['epa_descri' => $e['epa_descri'], 'epa_estado' => 1, 'epa_creado' => now()],
            );
        }
    }
}