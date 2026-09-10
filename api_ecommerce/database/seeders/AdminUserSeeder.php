<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Crea un usuario administrador de prueba para el login del panel CoreUI.
 * Requiere que CatalogoAuthSeeder y RolPermisoSeeder ya se hayan ejecutado.
 *
 * Login: admin@test.com / password
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $tipoDocumento = TipoDocumento::where('tpd_codigo', 'CI')->first();
        $cargo = Cargo::where('crg_descr', 'Administrador del Sistema')->first();
        $rolAdmin = Rol::where('rol_descri', 'Administrador')->first();

        if (! $tipoDocumento || ! $cargo || ! $rolAdmin) {
            $this->command?->warn(
                'AdminUserSeeder: ejecuta primero CatalogoAuthSeeder y RolPermisoSeeder.'
            );
            return;
        }

        $persona = Persona::updateOrCreate(
            ['per_cedula' => '0000000'],
            [
                'per_tpdid' => $tipoDocumento->tpd_id,
                'per_nombr1' => 'Admin',
                'per_apell1' => 'Principal',
                'per_estado' => 1,
            ]
        );

        Funcionario::updateOrCreate(
            ['fun_perid' => $persona->per_id],
            [
                'fun_crgid' => $cargo->crg_id,
                'fun_fecing' => now()->toDateString(),
                'fun_estado' => 1,
            ]
        );

        Usuario::updateOrCreate(
            ['usu_login' => 'admin@test.com'],
            [
                'usu_perid' => $persona->per_id,
                'usu_rolid' => $rolAdmin->rol_id,
                'usu_passwd' => bcrypt('password'),
                'usu_estado' => Usuario::ESTADO_ACTIVO,
            ]
        );
    }
}