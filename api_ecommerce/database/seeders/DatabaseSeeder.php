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

        // Catálogos base
        TipoComprobanteSeeder::class,
        TipoDocumentoSeeder::class,
        TipoClienteSeeder::class,
        TipoAjusteSeeder::class,
        TipoIvaSeeder::class,
        UnidadMedidaSeeder::class,
        MotivoNotaCreditoSeeder::class,

        // Ventas / pedidos
        TipoEntregaSeeder::class,
        MetodoPagoSeeder::class,
        ConfiguracionEnvioSeeder::class,
        EstadoPedidoSeeder::class,
        EstadoPagoSeeder::class,
       

        // Tienda y catálogo de productos
        ConfiguracionTiendaSeeder::class,
        CategoriaSeeder::class,
        AtributoFichaTecnicaSeeder::class,
        FaqTiendaSeeder::class,

            // Otros catálogos actuales del sistema
            TipoComprobanteSeeder::class,
            UnidadMedidaSeeder::class,
            MotivoNotaCreditoSeeder::class,   // <-- nuevo
            TipoEntregaSeeder::class,   // <-- nuevo
            MetodoPagoSeeder::class,   // <-- nuevo
            ConfiguracionEnvioSeeder::class,   // <-- nuevo (Configuración de Envío)
            EstadoPedidoSeeder::class,   // <-- nuevo
            EstadoPagoSeeder::class,     // <-- nuevo
            MotivoRechazoPagoSeeder::class,   // <-- nuevo
			  CatalogoSeeder::class,
        ]);
		
    }
}