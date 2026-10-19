<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Paso 4: se agrega EN_REVISION (pendiente de validación del admin antes de
 * enviarlo al cliente: control de precios).
 */
class EstadoPresupuestoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['epr_codigo' => 'BORRADOR',    'epr_descri' => 'Borrador (en edición del técnico)'],
            ['epr_codigo' => 'EN_REVISION', 'epr_descri' => 'Pendiente de validación'],
            ['epr_codigo' => 'ENVIADO',     'epr_descri' => 'Enviado al cliente'],
            ['epr_codigo' => 'APROBADO',    'epr_descri' => 'Aprobado por el cliente'],
            ['epr_codigo' => 'RECHAZADO',   'epr_descri' => 'Rechazado por el cliente'],
            ['epr_codigo' => 'VENCIDO',     'epr_descri' => 'Vencido sin respuesta'],
        ];

        foreach ($estados as $e) {
            DB::table('estado_presupuesto')->updateOrInsert(
                ['epr_codigo' => $e['epr_codigo']],
                ['epr_descri' => $e['epr_descri'], 'epr_estado' => 1, 'epr_creado' => now()],
            );
        }
    }
}
