<?php

namespace Database\Seeders;

use App\Models\Carrito\PlanCuota;
use Illuminate\Database\Seeder;

class PlanCuotaSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            ['pcu_cantidad' => 2, 'pcu_recargo_pct' => 7.50,  'pcu_orden' => 1, 'pcu_estado' => true],
            ['pcu_cantidad' => 3, 'pcu_recargo_pct' => 12.50, 'pcu_orden' => 2, 'pcu_estado' => true],
            ['pcu_cantidad' => 4, 'pcu_recargo_pct' => 20.00, 'pcu_orden' => 3, 'pcu_estado' => true],
            ['pcu_cantidad' => 5, 'pcu_recargo_pct' => 25.00, 'pcu_orden' => 4, 'pcu_estado' => true],
        ];

        // updateOrCreate sobre pcu_cantidad (que es UNIQUE): el seeder se
        // puede volver a correr sin duplicar planes ni perder ajustes de orden.
        foreach ($planes as $plan) {
            PlanCuota::updateOrCreate(
                ['pcu_cantidad' => $plan['pcu_cantidad']],
                $plan
            );
        }
    }
}