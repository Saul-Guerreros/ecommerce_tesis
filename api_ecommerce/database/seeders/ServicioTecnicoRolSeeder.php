<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Crea el rol "Técnico" y le asigna SOLO lo necesario para entrar al panel
 * y trabajar sus órdenes (T1-T4). Correr DESPUÉS de OpcionesAdminPermisoSeeder,
 * que es quien crea los permisos servicios.* a partir de OpcionesAdmin.
 *
 *   php artisan db:seed --class=ServicioTecnicoRolSeeder
 */
class ServicioTecnicoRolSeeder extends Seeder
{
    private const PERMISOS_TECNICO = [
        'dashboard.ver',         // requerido por el grupo admin de routes/api.php
        'servicios.tecnico.ver', // T1-T4
    ];

    public function run(): void
    {
        $tecnico = Rol::updateOrCreate(['rol_descri' => 'Técnico'], ['rol_estado' => 1]);

        $ids = Permiso::whereIn('prm_descri', self::PERMISOS_TECNICO)->pluck('prm_id')->all();

        if (count($ids) !== count(self::PERMISOS_TECNICO)) {
            $this->command?->warn('Faltan permisos: corré OpcionesAdminPermisoSeeder antes.');
        }

        $tecnico->permisos()->syncWithoutDetaching($ids);
    }
}
