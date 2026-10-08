<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Módulo 1 - Autenticación y Perfil
            CatalogoAuthSeeder::class,
            RolPermisoSeeder::class,
            AdminUserSeeder::class,
            ConfiguracionPrecioSeeder::class,

            // Otros catálogos actuales del sistema
            TipoComprobanteSeeder::class,
            UnidadMedidaSeeder::class,
            MotivoNotaCreditoSeeder::class,   // <-- nuevo
            TipoEntregaSeeder::class,   // <-- nuevo
            MetodoPagoSeeder::class,   // <-- nuevo
            ConfiguracionEnvioSeeder::class,   // <-- nuevo (Configuración de Envío)
            EstadoPedidoSeeder::class,   // <-- nuevo
            EstadoPagoSeeder::class,     // <-- nuevo
        ]);
    }
}