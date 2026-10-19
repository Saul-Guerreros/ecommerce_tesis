<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Depende de TipoDispositivoSeeder. tipo = null => falla genérica.
 */
class FallaSeeder extends Seeder
{
    public function run(): void
    {
        $fallas = [
            // Genéricas
            ['fll_codigo' => 'NO_ENCIENDE',  'fll_descri' => 'No enciende',                          'tipo' => null],
            ['fll_codigo' => 'SE_APAGA',     'fll_descri' => 'Se apaga o reinicia solo',             'tipo' => null],
            ['fll_codigo' => 'LENTITUD',     'fll_descri' => 'Funcionamiento lento',                 'tipo' => null],
            ['fll_codigo' => 'SOBRECALENT',  'fll_descri' => 'Sobrecalentamiento',                   'tipo' => null],
            ['fll_codigo' => 'SIN_VIDEO',    'fll_descri' => 'Sin imagen / sin video',               'tipo' => null],
            ['fll_codigo' => 'SO_DANADO',    'fll_descri' => 'Sistema operativo dañado o no inicia', 'tipo' => null],
            ['fll_codigo' => 'VIRUS',        'fll_descri' => 'Infección de malware',                 'tipo' => null],
            ['fll_codigo' => 'DANO_LIQUIDO', 'fll_descri' => 'Daño por líquido',                     'tipo' => null],
            // Notebook
            ['fll_codigo' => 'NB_PANTALLA',  'fll_descri' => 'Pantalla rota o con líneas',           'tipo' => 'NOTEBOOK'],
            ['fll_codigo' => 'NB_TECLADO',   'fll_descri' => 'Teclado con teclas que no responden',  'tipo' => 'NOTEBOOK'],
            ['fll_codigo' => 'NB_BATERIA',   'fll_descri' => 'Batería no carga o dura poco',         'tipo' => 'NOTEBOOK'],
            ['fll_codigo' => 'NB_BISAGRA',   'fll_descri' => 'Bisagra rota',                         'tipo' => 'NOTEBOOK'],
            // Impresora
            ['fll_codigo' => 'IMP_ATASCO',   'fll_descri' => 'Atasco de papel',                      'tipo' => 'IMPRESORA'],
            ['fll_codigo' => 'IMP_CALIDAD',  'fll_descri' => 'Impresión con manchas o falta de color', 'tipo' => 'IMPRESORA'],
        ];

        $tipos = DB::table('tipo_dispositivo')->pluck('tdi_id', 'tdi_codigo');

        foreach ($fallas as $f) {
            DB::table('falla')->updateOrInsert(
                ['fll_codigo' => $f['fll_codigo']],
                [
                    'fll_descri' => $f['fll_descri'],
                    'fll_tdiid'  => $f['tipo'] ? ($tipos[$f['tipo']] ?? null) : null,
                    'fll_estado' => 1,
                    'fll_creado' => now(),
                ],
            );
        }
    }
}
