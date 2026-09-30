<?php

namespace Database\Seeders;

use App\Models\Configuracion\ConfiguracionTienda;
use Illuminate\Database\Seeder;

class ConfiguracionTiendaSeeder extends Seeder
{
    /**
     * Fila única (singleton) con los valores iniciales de la tienda.
     * Idempotente: si ya existe la fila no hace nada, así se puede correr
     * php artisan db:seed más de una vez sin duplicar ni pisar lo que el
     * administrador ya configuró.
     */
    public function run(): void
    {
        if (ConfiguracionTienda::query()->exists()) {
            return;
        }

        ConfiguracionTienda::create(ConfiguracionTienda::VALORES_INICIALES);
    }
}