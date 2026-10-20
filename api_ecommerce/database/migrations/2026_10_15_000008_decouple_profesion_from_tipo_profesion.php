<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desacopla profesion de tipo_profesion.
 *
 * El diseño original asumía una jerarquía (tipo_profesion agrupaba
 * profesiones, y el segundo desplegable se filtraba por el primero).
 * Ese modelo no aplica a los valores reales del negocio: tipo_profesion
 * guarda la RELACIÓN LABORAL (Funcionario Público / Funcionario Privado
 * / Independiente) y profesion guarda el OFICIO. Son dos preguntas
 * distintas sobre la misma persona, no padre-hijo: un Médico puede ser
 * funcionario público, privado o independiente, y las tres
 * combinaciones son válidas.
 *
 * Cambios:
 *  1. profesion pierde pro_tprid: pasa a ser un catálogo plano. Su
 *     UNIQUE deja de ser (pro_tprid, pro_descri) y pasa a ser pro_descri
 *     a secas -- ya no hay un "Médicos" por cada tipo, hay uno solo.
 *  2. solicitud_credito gana scr_tprid: la relación laboral declarada
 *     pasa a ser un dato propio de la solicitud, con su FK, en vez de
 *     deducirse de la profesión elegida.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 1. profesion: catálogo plano ----------
        Schema::table('profesion', function (Blueprint $table) {
            $table->dropForeign('fk_pro_tpr');
            $table->dropUnique('uq_pro_tpr_descri');
            $table->dropIndex('ix_pro_tprid');
            $table->dropColumn('pro_tprid');
        });

        Schema::table('profesion', function (Blueprint $table) {
            $table->unique('pro_descri', 'uq_pro_descri');
        });

        // ---------- 2. solicitud_credito: relación laboral propia ----------
        Schema::table('solicitud_credito', function (Blueprint $table) {
            $table->unsignedSmallInteger('scr_tprid')->nullable()->after('scr_estciv');

            $table->foreign('scr_tprid', 'fk_scr_tpr')
                  ->references('tpr_id')->on('tipo_profesion')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        // scr_tprid es obligatorio, pero una columna NOT NULL no se puede
        // agregar a una tabla que ya tenga filas sin un valor de relleno
        // -- y no existe un valor por defecto razonable para la relación
        // laboral de una solicitud ya presentada. Si la tabla está vacía
        // (caso normal en desarrollo), se marca NOT NULL acá mismo; si ya
        // hay solicitudes, queda nullable y se debe completar a mano antes
        // de endurecerla.
        if (DB::table('solicitud_credito')->count() === 0) {
            DB::statement('ALTER TABLE solicitud_credito ALTER COLUMN scr_tprid SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('solicitud_credito', function (Blueprint $table) {
            $table->dropForeign('fk_scr_tpr');
            $table->dropColumn('scr_tprid');
        });

        Schema::table('profesion', function (Blueprint $table) {
            $table->dropUnique('uq_pro_descri');
            $table->unsignedSmallInteger('pro_tprid')->nullable();
        });

        Schema::table('profesion', function (Blueprint $table) {
            $table->foreign('pro_tprid', 'fk_pro_tpr')
                  ->references('tpr_id')->on('tipo_profesion')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->unique(['pro_tprid', 'pro_descri'], 'uq_pro_tpr_descri');
            $table->index('pro_tprid', 'ix_pro_tprid');
        });
    }
};