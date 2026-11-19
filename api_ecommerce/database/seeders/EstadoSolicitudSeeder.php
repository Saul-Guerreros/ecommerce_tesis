<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estados por los que pasa una solicitud de credito.
 *
 * Los CODIGOS no cambian nunca: son lo que lee el codigo PHP. Lo que se
 * ajusta acá son las DESCRIPCIONES, para que el badge del panel admin
 * diga lo mismo que el documento de diseño (RECIBIDA / EN EVALUACION).
 *
 * ACEPTADA corresponde a la etapa D (el cliente acepta las condiciones
 * del credito aprobado). Se crea ahora para que la maquina de estados
 * quede completa junto con scr_fecace; el panel admin todavia no la
 * genera.
 *
 * updateOrInsert y no insert: el seeder tiene que poder correrse de
 * nuevo sobre una base ya cargada sin romper el UNIQUE de esc_codigo.
 */
class EstadoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['esc_codigo' => 'PENDIENTE',   'esc_descri' => 'RECIBIDA',       'esc_orden' => 1],
            ['esc_codigo' => 'EN_REVISION', 'esc_descri' => 'EN EVALUACIÓN',  'esc_orden' => 2],
            ['esc_codigo' => 'APROBADA',    'esc_descri' => 'APROBADA',       'esc_orden' => 3],
            ['esc_codigo' => 'ACEPTADA',    'esc_descri' => 'ACEPTADA',       'esc_orden' => 4],
            ['esc_codigo' => 'RECHAZADA',   'esc_descri' => 'RECHAZADA',      'esc_orden' => 5],
            ['esc_codigo' => 'ANULADA',     'esc_descri' => 'ANULADA',        'esc_orden' => 6],
            ['esc_codigo' => 'CONVERTIDA',  'esc_descri' => 'PEDIDO GENERADO', 'esc_orden' => 7],

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