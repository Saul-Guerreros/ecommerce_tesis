<?php

namespace Database\Seeders;

use App\Models\TipoIva;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

/**
 * Catálogos base para los campos nuevos de `producto` (prd_tivid,
 * prd_umdid). La migración 2026_09_01_000004 ya crea un registro "por
 * defecto" de cada uno para poder migrar productos existentes; este
 * seeder agrega opciones adicionales reales para que los <select> del
 * formulario no queden con una sola alternativa.
 */
class CatalogoProductoSeeder extends Seeder
{
    public function run(): void
    {
        $tiposIva = [
            ['tiv_descri' => 'Exenta', 'tiv_tasa' => 0],
            ['tiv_descri' => 'IVA 5%', 'tiv_tasa' => 5],
            ['tiv_descri' => 'IVA 10%', 'tiv_tasa' => 10],
        ];

        foreach ($tiposIva as $tipo) {
            TipoIva::firstOrCreate(
                ['tiv_descri' => $tipo['tiv_descri']],
                ['tiv_tasa' => $tipo['tiv_tasa'], 'tiv_estado' => 1]
            );
        }

        $unidades = [
            ['umd_codigo' => 'UN', 'umd_nombre' => 'Unidad', 'umd_decimal' => 0],
            ['umd_codigo' => 'CJ', 'umd_nombre' => 'Caja', 'umd_decimal' => 0],
            ['umd_codigo' => 'KG', 'umd_nombre' => 'Kilogramo', 'umd_decimal' => 1],
            ['umd_codigo' => 'MT', 'umd_nombre' => 'Metro', 'umd_decimal' => 1],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::firstOrCreate(
                ['umd_codigo' => $unidad['umd_codigo']],
                ['umd_nombre' => $unidad['umd_nombre'], 'umd_decimal' => $unidad['umd_decimal'], 'umd_estado' => 1]
            );
        }
    }
}
