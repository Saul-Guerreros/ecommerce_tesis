<?php

namespace Database\Seeders;

use App\Models\Servicio\TipoDispositivo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Punto 7 - Catálogos de la clasificación automática.
 * Correr DESPUÉS de TipoDispositivoSeeder y FallaSeeder:
 *   php artisan db:seed --class=ClasificacionServicioSeeder
 */
class ClasificacionServicioSeeder extends Seeder
{
    public function run(): void
    {
        $this->categorias();
        $this->urgencias();
        $this->dispositivoPendiente();
        $this->asignarCategoriasAFallas();
    }

    private function categorias(): void
    {
        $categorias = [
            'HARDWARE'      => 'Hardware y componentes',
            'ENERGIA'       => 'Energía y batería',
            'PANTALLA'      => 'Pantalla y video',
            'SOFTWARE'      => 'Software y sistema operativo',
            'MALWARE'       => 'Virus y seguridad',
            'RED'           => 'Red y conectividad',
            'DATOS'         => 'Datos y almacenamiento',
            'IMPRESION'     => 'Impresión',
            'MANTENIMIENTO' => 'Mantenimiento y limpieza',
            'OTRO'          => 'Otro',
        ];

        foreach ($categorias as $codigo => $descri) {
            DB::table('categoria_falla')->updateOrInsert(
                ['caf_codigo' => $codigo],
                ['caf_descri' => $descri, 'caf_estado' => 1, 'caf_creado' => now()],
            );
        }
    }

    private function urgencias(): void
    {
        $urgencias = [
            ['ALTA', 'Alta', 'danger', 1,
             'Riesgo de pérdida de datos, el equipo no enciende, daño por líquido, sobrecalentamiento o equipo de uso laboral/académico imprescindible.'],
            ['MEDIA', 'Media', 'warning', 2,
             'El equipo funciona pero con fallas que impiden un uso normal (pantalla, teclado, batería, sistema inestable, impresión defectuosa).'],
            ['BAJA', 'Baja', 'secondary', 3,
             'Mantenimiento preventivo, lentitud leve, detalles estéticos, consultas o instalaciones sin urgencia.'],
        ];

        foreach ($urgencias as [$codigo, $descri, $color, $orden, $criterio]) {
            DB::table('urgencia_servicio')->updateOrInsert(
                ['urg_codigo' => $codigo],
                ['urg_descri' => $descri, 'urg_criter' => $criterio, 'urg_color' => $color,
                 'urg_orden' => $orden, 'urg_estado' => 1, 'urg_creado' => now()],
            );
        }
    }

    /** Registro centinela: la solicitud se crea con este tipo hasta que la IA (o un técnico) lo determine. */
    private function dispositivoPendiente(): void
    {
        DB::table('tipo_dispositivo')->updateOrInsert(
            ['tdi_codigo' => TipoDispositivo::PENDIENTE],
            ['tdi_descri' => 'Por determinar', 'tdi_estado' => 1, 'tdi_creado' => now()],
        );
    }

    private function asignarCategoriasAFallas(): void
    {
        $mapa = [
            'NO_ENCIENDE'  => 'ENERGIA',
            'SE_APAGA'     => 'ENERGIA',
            'LENTITUD'     => 'SOFTWARE',
            'SOBRECALENT'  => 'MANTENIMIENTO',
            'SIN_VIDEO'    => 'PANTALLA',
            'SO_DANADO'    => 'SOFTWARE',
            'VIRUS'        => 'MALWARE',
            'DANO_LIQUIDO' => 'HARDWARE',
            'NB_PANTALLA'  => 'PANTALLA',
            'NB_TECLADO'   => 'HARDWARE',
            'NB_BATERIA'   => 'ENERGIA',
            'NB_BISAGRA'   => 'HARDWARE',
            'IMP_ATASCO'   => 'IMPRESION',
            'IMP_CALIDAD'  => 'IMPRESION',
        ];

        $categorias = DB::table('categoria_falla')->pluck('caf_id', 'caf_codigo');

        foreach ($mapa as $falla => $categoria) {
            DB::table('falla')
                ->where('fll_codigo', $falla)
                ->whereNull('fll_cafid')          // no pisa lo que el admin ya asignó
                ->update(['fll_cafid' => $categorias[$categoria]]);
        }
    }
}