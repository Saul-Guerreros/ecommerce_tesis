<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadoPedidoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['est_codigo' => 'PENDIENTE',      'est_descri' => 'Pendiente'],
            ['est_codigo' => 'EN_PREPARACION', 'est_descri' => 'En preparación'],
            ['est_codigo' => 'LISTO',          'est_descri' => 'Listo para entrega/retiro'],
            ['est_codigo' => 'ENTREGADO',      'est_descri' => 'Entregado'],
            ['est_codigo' => 'CANCELADO',      'est_descri' => 'Cancelado'],
        ];

        foreach ($estados as $e) {
            DB::table('estado_pedido')->updateOrInsert(
                ['est_codigo' => $e['est_codigo']],
                ['est_descri' => $e['est_descri'], 'est_estado' => 1, 'est_creado' => now()],
            );
        }
    }
}