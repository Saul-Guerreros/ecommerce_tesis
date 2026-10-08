<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta direccion al Checkout - Paso 1: Departamento y Ciudad se siguen
 * resolviendo del catalogo (dir_ciuid -> ciudad -> departamento); Barrio,
 * Calle y Referencia pasan a texto libre, porque mantener ese catalogo
 * completo para todo el pais es inviable.
 *
 * dir_barid y dir_calid quedan NULLABLE y deprecados, no se eliminan:
 * BarrioController y CalleController los declaran en su constante
 * DEPENDENCIAS para bloquear borrados, y las direcciones viejas los usan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direccion', function (Blueprint $table) {
            $table->string('dir_alias', 50)->nullable()->after('dir_perid');
            $table->unsignedInteger('dir_ciuid')->nullable()->after('dir_alias');
            $table->string('dir_barrio', 60)->nullable()->after('dir_ciuid');
            $table->string('dir_calle', 100)->nullable()->after('dir_barrio');

            $table->foreign('dir_ciuid', 'fk_dir_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('dir_ciuid', 'ix_dir_ciuid');
        });

        // Backfill: las direcciones ya cargadas conservan su informacion,
        // tomada del catalogo barrio/calle que tenian antes.
        DB::statement("
            UPDATE direccion d
               SET dir_ciuid  = b.bar_ciuid,
                   dir_barrio = b.bar_descri
              FROM barrio b
             WHERE d.dir_barid = b.bar_id
               AND d.dir_ciuid IS NULL
        ");

        DB::statement("
            UPDATE direccion d
               SET dir_calle = c.cal_descri
              FROM calle c
             WHERE d.dir_calid = c.cal_id
               AND d.dir_calle IS NULL
        ");

        DB::statement("UPDATE direccion SET dir_alias = 'Mi Casa' WHERE dir_alias IS NULL");

        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_barid DROP NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_calid DROP NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_ciuid SET NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_barrio SET NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_calle SET NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_alias SET NOT NULL');

        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_referencia TYPE VARCHAR(250)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_referencia TYPE VARCHAR(100)');

        Schema::table('direccion', function (Blueprint $table) {
            $table->dropForeign('fk_dir_ciu');
            $table->dropIndex('ix_dir_ciuid');
            $table->dropColumn(['dir_alias', 'dir_ciuid', 'dir_barrio', 'dir_calle']);
        });

        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_barid SET NOT NULL');
        DB::statement('ALTER TABLE direccion ALTER COLUMN dir_calid SET NOT NULL');
    }
};