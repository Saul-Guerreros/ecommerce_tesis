<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que nacen en el PANEL ADMIN, no en el wizard del cliente.
 *
 * observaciones_internas NO lleva columna nueva: scr_observ ya existe
 * desde la migracion 000005 documentada como "uso del panel admin
 * (resolucion)". Dos columnas con el mismo significado terminan
 * desincronizandose en el primer cambio.
 *
 * scr_fecpri es la fecha REAL de la primera cuota, confirmada por el
 * administrador. El cliente ve una estimada al solicitar; el cronograma
 * firme recien existe cuando se aprueba.
 *
 * El motivo de rechazo se guarda como codigo corto + CHECK, mismo
 * criterio que scr_estciv y scr_tipviv: catalogo chico y cerrado no
 * justifica una tabla referencial propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_credito', function (Blueprint $table) {
            // ---------- Condiciones confirmadas al aprobar ----------
            $table->date('scr_fecpri')->nullable()->after('scr_cuomen');
            $table->date('scr_vighas')->nullable()->after('scr_fecpri');

            // ---------- Resolucion ----------
            $table->string('scr_motrec', 30)->nullable()->after('scr_observ');
            $table->string('scr_detrec', 300)->nullable()->after('scr_motrec');
            $table->timestamp('scr_fecdec')->nullable()->after('scr_detrec');
            $table->unsignedBigInteger('scr_usudec')->nullable()->after('scr_fecdec');

            // ---------- Aceptacion del cliente (etapa D) ----------
            $table->timestamp('scr_fecace')->nullable()->after('scr_usudec');
            $table->string('scr_lugfir', 150)->nullable()->after('scr_fecace');

            $table->foreign('scr_usudec', 'fk_scr_usudec')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('scr_fecdec', 'ix_scr_fecdec');
        });

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_motrec
            CHECK (scr_motrec IS NULL
                   OR scr_motrec IN ('INGRESOS','ANTIGUEDAD','DATOS','MONTO','OTRO'))");

        // Quien decidio y cuando van siempre juntos: una resolucion sin
        // autor no es auditable.
        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_decision
            CHECK ((scr_fecdec IS NULL) = (scr_usudec IS NULL))");

        // No puede haber oferta vigente sin una decision que la haya creado.
        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_vigencia
            CHECK (scr_vighas IS NULL OR scr_fecdec IS NOT NULL)");

        // El cliente no puede aceptar antes de que exista la resolucion.
        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_aceptacion
            CHECK (scr_fecace IS NULL OR scr_fecdec IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_aceptacion');
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_vigencia');
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_decision');
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_motrec');

        Schema::table('solicitud_credito', function (Blueprint $table) {
            $table->dropForeign('fk_scr_usudec');
            $table->dropIndex('ix_scr_fecdec');
            $table->dropColumn([
                'scr_fecpri', 'scr_vighas', 'scr_motrec', 'scr_detrec',
                'scr_fecdec', 'scr_usudec', 'scr_fecace', 'scr_lugfir',
            ]);
        });
    }
};