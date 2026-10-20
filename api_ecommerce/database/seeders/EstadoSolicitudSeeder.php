<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estados por los que pasa una solicitud de credito.
 *
 * PENDIENTE es el estado con el que nace (lo que ve el cliente como
 * "esta siendo revisada"). EN_REVISION lo pone el administrador cuando
 * la toma; APROBADA / RECHAZADA la cierran. ANULADA es la baja a
 * pedido del propio cliente antes de que se resuelva.
 *
 * updateOrInsert y no insert: el seeder tiene que poder correrse de
 * nuevo sobre una base ya cargada sin romper el UNIQUE de esc_codigo.
 */
class EstadoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['esc_codigo' => 'PENDIENTE',   'esc_descri' => 'PENDIENTE DE REVISION', 'esc_orden' => 1],
            ['esc_codigo' => 'EN_REVISION', 'esc_descri' => 'EN REVISION',           'esc_orden' => 2],
            ['esc_codigo' => 'APROBADA',    'esc_descri' => 'APROBADA',              'esc_orden' => 3],
            ['esc_codigo' => 'RECHAZADA',   'esc_descri' => 'RECHAZADA',             'esc_orden' => 4],
            ['esc_codigo' => 'ANULADA',     'esc_descri' => 'ANULADA POR EL CLIENTE','esc_orden' => 5],
        ];

        foreach ($estados as $estado) {
            DB::table('estado_solicitud')->updateOrInsert(
                ['esc_codigo' => $estado['esc_codigo']],
                [
                    'esc_descri' => $estado['esc_descri'],
                    'esc_orden'  => $estado['esc_orden'],
                    'esc_estado' => 1,
                    'esc_creado' => now(),
                ]
            );
        }
    }
}