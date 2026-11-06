<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ets_visible agrupa los estados internos en lo que ve el cliente.
 * SOLICITADO y CANCELADO quedan fuera del timeline de 5 pasos de C4.
 * GESTION_GARANTIA y EN_EMPRESA solo los recorre la garantía de compra (gestión con la empresa).
 */
class EstadoServicioSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['ets_codigo' => 'SOLICITADA',     'ets_descri' => 'Solicitud registrada',          'ets_visible' => 'SOLICITADO',     'ets_orden' => 1],
            ['ets_codigo' => 'RECIBIDA',       'ets_descri' => 'Equipo recibido en el taller',  'ets_visible' => 'RECIBIDO',       'ets_orden' => 2],
            ['ets_codigo' => 'EN_DIAGNOSTICO', 'ets_descri' => 'En diagnóstico',                'ets_visible' => 'EN DIAGNÓSTICO', 'ets_orden' => 3],
            ['ets_codigo' => 'PRESUPUESTADA',  'ets_descri' => 'Presupuesto enviado al cliente', 'ets_visible' => 'EN DIAGNÓSTICO', 'ets_orden' => 4],
            ['ets_codigo' => 'APROBADA',       'ets_descri' => 'Presupuesto aprobado',          'ets_visible' => 'EN REPARACIÓN',  'ets_orden' => 5],
            ['ets_codigo' => 'EN_REPARACION',  'ets_descri' => 'En reparación',                 'ets_visible' => 'EN REPARACIÓN',  'ets_orden' => 6],
            ['ets_codigo' => 'GESTION_GARANTIA', 'ets_descri' => 'Gestión de garantía con la empresa',     'ets_visible' => 'EN GESTIÓN',     'ets_orden' => 7],
            ['ets_codigo' => 'EN_EMPRESA',       'ets_descri' => 'En la empresa responsable de la garantía', 'ets_visible' => 'EN LA EMPRESA', 'ets_orden' => 8],
            ['ets_codigo' => 'LISTA',          'ets_descri' => 'Lista para entregar',           'ets_visible' => 'LISTO',          'ets_orden' => 9],
            ['ets_codigo' => 'ENTREGADA',      'ets_descri' => 'Entregada al cliente',          'ets_visible' => 'ENTREGADO',      'ets_orden' => 10],
            ['ets_codigo' => 'CANCELADA',      'ets_descri' => 'Cancelada',                     'ets_visible' => 'CANCELADO',      'ets_orden' => 11],
        ];

        foreach ($estados as $e) {
            DB::table('estado_servicio')->updateOrInsert(
                ['ets_codigo' => $e['ets_codigo']],
                [
                    'ets_descri'  => $e['ets_descri'],
                    'ets_visible' => $e['ets_visible'],
                    'ets_orden'   => $e['ets_orden'],
                    'ets_estado'  => 1,
                    'ets_creado'  => now(),
                ],
            );
        }
    }
}
