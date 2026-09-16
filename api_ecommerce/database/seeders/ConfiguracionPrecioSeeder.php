<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionPrecio;

class ConfiguracionPrecioSeeder extends Seeder
{
    /**
     * Fila única (singleton) con los valores iniciales de markup general.
     * Ajustar estos valores a los reales del negocio antes de la defensa.
     * Idempotente: si ya existe la fila, no hace nada (evita duplicar al
     * correr php artisan db:seed más de una vez).
     */
    public function run(): void
    {
        if (ConfiguracionPrecio::query()->exists()) {
            return;
        }

        ConfiguracionPrecio::create([
            'cpr_marmin'    => 30,   // margen minorista general (%)
            'cpr_marmaj'    => 15,   // margen mayorista general (%)
            'cpr_canminmaj' => 10,   // cantidad mínima mayorista general
        ]);
    }
}
