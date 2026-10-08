<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfiguracionEnvioSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('configuracion_envio')->count() === 0) {
            DB::table('configuracion_envio')->insert([
                'cfe_modo'       => 'fijo',
                'cfe_costo_fijo' => 0,
                'cfe_creado'     => now(),
                'cfe_actualizado'=> now(),
            ]);
        }
    }
}