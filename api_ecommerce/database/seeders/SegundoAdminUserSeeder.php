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
 * Crea un segundo usuario administrador de prueba para el login del panel CoreUI.
 * Requiere que CatalogoAuthSeeder y RolPermisoSeeder ya se hayan ejecutado.
 *
 * Login: admin2@test.com / password
 */
class SegundoAdminUserSeeder extends Seeder
{
    private const CEDULA   = '0000001';
    private const NOMBRE   = 'Admin';
    private const APELLIDO = 'Secundario';
    private const LOGIN    = 'admin@gmail.com';
    private const PASSWORD = '123';

    public function run(): void
    {
        $tipoDocumento = TipoDocumento::where('tpd_codigo', 'CI')->first();
        $cargo = Cargo::where('crg_descr', 'Administrador del Sistema')->first();
        $rolAdmin = Rol::where('rol_descri', 'Administrador')->first();

        if (! $tipoDocumento || ! $cargo || ! $rolAdmin) {
            $this->command?->warn(
                'SegundoAdminUserSeeder: ejecuta primero CatalogoAuthSeeder y RolPermisoSeeder.'
            );
            return;
        }

        $persona = Persona::updateOrCreate(
            ['per_cedula' => self::CEDULA],
            [
                'per_tpdid' => $tipoDocumento->tpd_id,
                'per_nombr1' => self::NOMBRE,
                'per_apell1' => self::APELLIDO,
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
            ['usu_login' => self::LOGIN],
            [
                'usu_perid' => $persona->per_id,
                'usu_rolid' => $rolAdmin->rol_id,
                'usu_passwd' => bcrypt(self::PASSWORD),
                'usu_estado' => Usuario::ESTADO_ACTIVO,
            ]
        );

        $this->command?->info('Usuario admin creado: ' . self::LOGIN);
    }
}
