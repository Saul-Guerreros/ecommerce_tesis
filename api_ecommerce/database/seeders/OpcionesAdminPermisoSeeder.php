<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\OpcionesAdmin;
use Illuminate\Database\Seeder;

/**
 * Crea los permisos de acceso del catálogo OpcionesAdmin y los asigna.
 *
 *   php artisan db:seed --class=OpcionesAdminPermisoSeeder
 *
 * Es seguro volver a correrlo: el Vendedor solo recibe los permisos que se
 * crean en ESTA ejecución, así que no pisa lo que ya ajustaste en la pantalla.
 */
class OpcionesAdminPermisoSeeder extends Seeder
{
    /** Mismo criterio que RolPermisoSeeder, más las pantallas de seguridad nuevas. */
    private const VETADOS_VENDEDOR = [
        'usuarios.ver', 'usuarios.gestionar',
        'roles.gestionar', 'permisos.gestionar',
        'chatbot.gestionar', 'auditoria.ver',
        // Información financiera y de gestión: solo por asignación explícita
        'metricas.dashboard.ver',
        'reportes.ventas.ver', 'reportes.compras.ver', 'reportes.inventario.ver',
        'reportes.servicio.ver', 'reportes.clientes.ver', 'reportes.promociones.ver',
        'reportes.chatbot.ver',
    ];

    public function run(): void
    {
        $nuevos = [];

        foreach (OpcionesAdmin::codigos() as $codigo) {
            $permiso = Permiso::firstOrCreate(['prm_descri' => $codigo], ['prm_estado' => 1]);

            if ($permiso->wasRecentlyCreated) {
                $nuevos[] = $codigo;
            }
        }

        // Administrador: todo (igual que RolPermisoSeeder).
        Rol::where('rol_descri', 'Administrador')->first()
            ?->permisos()->syncWithoutDetaching(Permiso::pluck('prm_id')->all());

        // Vendedor: conserva el acceso que ya tenía (las opciones nuevas, salvo las vetadas).
        $vendedor = Rol::where('rol_descri', 'Vendedor')->first();
        $paraVendedor = array_diff($nuevos, self::VETADOS_VENDEDOR);

        if ($vendedor && $paraVendedor) {
            $vendedor->permisos()->syncWithoutDetaching(
                Permiso::whereIn('prm_descri', $paraVendedor)->pluck('prm_id')->all()
            );
        }

        $this->command?->info(count($nuevos) . ' permiso(s) nuevo(s) creado(s).');
    }
}
