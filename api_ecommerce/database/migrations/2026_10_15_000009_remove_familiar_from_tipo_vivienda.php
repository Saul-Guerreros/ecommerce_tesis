<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita 'FAMILIAR' del catálogo de tipo de vivienda: quedan solo
 * 'PROPIA' y 'ALQUILER'.
 *
 * ck_scr_vivienda NO se toca: su regla ("los datos ANDE/ESSAP solo
 * existen con vivienda propia") sigue siendo correcta con dos valores,
 * igual que lo era con tres.
 *
 * Si hubiera solicitudes ya cargadas con 'FAMILIAR', el nuevo CHECK las
 * rechazaría y la migración fallaría. Se reasignan a 'ALQUILER' antes de
 * recrear la restricción: es el valor más cercano en términos de
 * evaluación crediticia (vivienda no propia) y evita que la migración
 * trabe el despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('solicitud_credito')
            ->where('scr_tipviv', 'FAMILIAR')
            ->update(['scr_tipviv' => 'ALQUILER']);

        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_tipviv');

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_tipviv
            CHECK (scr_tipviv IN ('PROPIA','ALQUILER'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_tipviv');

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_tipviv
            CHECK (scr_tipviv IN ('PROPIA','ALQUILER','FAMILIAR'))");
    }
};