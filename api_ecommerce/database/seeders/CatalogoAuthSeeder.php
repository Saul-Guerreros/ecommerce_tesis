<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

/**
 * Catalogos base que el modulo de Autenticacion y Perfil necesita para
 * poder registrar personas/funcionarios/clientes (tipo_documento,
 * tipo_cliente, cargo). Los catalogos geograficos (departamento, ciudad,
 * barrio, calle) se dejan vacios a proposito.
 */
class CatalogoAuthSeeder extends Seeder
{
    public function run(): void
    {
        $tiposDocumento = [
            ['tpd_codigo' => 'CI', 'tpd_descri' => 'Cedula de Identidad'],
            ['tpd_codigo' => 'PAS', 'tpd_descri' => 'Pasaporte'],
            ['tpd_codigo' => 'RUC', 'tpd_descri' => 'RUC'],
        ];

        foreach ($tiposDocumento as $tipo) {
            TipoDocumento::updateOrCreate(
                ['tpd_codigo' => $tipo['tpd_codigo']],
                ['tpd_descri' => $tipo['tpd_descri'], 'tpd_estado' => 1]
            );
        }

        $tiposCliente = [
            'Persona Fisica',
            'Persona Juridica',
        ];

        foreach ($tiposCliente as $descripcion) {
            TipoCliente::updateOrCreate(
                ['tpc_descri' => $descripcion],
                ['tpc_estado' => 1]
            );
        }

        $cargos = [
            'Administrador del Sistema',
            'Vendedor',
        ];

        foreach ($cargos as $descripcion) {
            Cargo::updateOrCreate(
                ['crg_descr' => $descripcion],
                ['crg_estado' => 1]
            );
        }
    }
}
