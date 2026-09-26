<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivoNotaCreditoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            ['mnc_codigo' => 'DESC', 'mnc_descri' => 'Descuento o bonificación posterior', 'mnc_afesto' => 0],
            ['mnc_codigo' => 'EPRE', 'mnc_descri' => 'Error en precio facturado',          'mnc_afesto' => 0],
            ['mnc_codigo' => 'ECAN', 'mnc_descri' => 'Diferencia de cantidad facturada',   'mnc_afesto' => 1],
            ['mnc_codigo' => 'DDEF', 'mnc_descri' => 'Devolución por producto defectuoso', 'mnc_afesto' => 1],
            ['mnc_codigo' => 'DOTR', 'mnc_descri' => 'Devolución por otros motivos',       'mnc_afesto' => 1],
        ];

        foreach ($motivos as $motivo) {
            DB::table('motivo_nota_credito')->updateOrInsert(
                ['mnc_codigo' => $motivo['mnc_codigo']],
                $motivo + ['mnc_estado' => 1]
            );
        }
    }
}