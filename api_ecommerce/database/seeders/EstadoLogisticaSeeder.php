<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadoLogisticaSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            // Ingreso del equipo al taller (sse_elging)
            ['elg_codigo' => 'PENDIENTE',       'elg_descri' => 'Pendiente de ingreso',   'elg_ambito' => 'INGRESO'],
            ['elg_codigo' => 'RETIRO_AGENDADO', 'elg_descri' => 'Retiro agendado',        'elg_ambito' => 'INGRESO'],
            ['elg_codigo' => 'EN_TRANSITO',     'elg_descri' => 'En tránsito al taller',  'elg_ambito' => 'INGRESO'],
            ['elg_codigo' => 'RECIBIDO',        'elg_descri' => 'Recibido en el taller',  'elg_ambito' => 'INGRESO'],
            // Egreso del equipo al cliente (sse_elgegr)
            ['elg_codigo' => 'NO_DEFINIDO',     'elg_descri' => 'Entrega no definida',    'elg_ambito' => 'EGRESO'],
            ['elg_codigo' => 'ESPERA_RETIRO',   'elg_descri' => 'Esperando retiro',       'elg_ambito' => 'EGRESO'],
            ['elg_codigo' => 'DESPACHADO',      'elg_descri' => 'Despachado al cliente',  'elg_ambito' => 'EGRESO'],
            ['elg_codigo' => 'ENTREGADO',       'elg_descri' => 'Entregado al cliente',   'elg_ambito' => 'EGRESO'],
        ];

        foreach ($estados as $e) {
            DB::table('estado_logistica')->updateOrInsert(
                ['elg_codigo' => $e['elg_codigo']],
                [
                    'elg_descri' => $e['elg_descri'],
                    'elg_ambito' => $e['elg_ambito'],
                    'elg_estado' => 1,
                    'elg_creado' => now(),
                ],
            );
        }
    }
}
