<?php
// database/migrations/2026_08_31_110003_create_timbrado_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timbrado', function (Blueprint $table) {
            $table->increments('tim_id');
            $table->unsignedInteger('tim_empid');
            $table->unsignedInteger('tim_tcoid');
            $table->unsignedInteger('tim_sucid');

            $table->char('tim_numero', 8);      // numero de timbrado otorgado
            $table->char('tim_establec', 3);    // establecimiento     (001)
            $table->char('tim_puntexp', 3);     // punto de expedicion (001)

            $table->date('tim_fechin');         // inicio de vigencia
            $table->date('tim_fechfn');         // fin de vigencia

            $table->integer('tim_nroini');      // primer numero habilitado
            $table->integer('tim_nrofin');      // ultimo numero habilitado
            $table->integer('tim_nroact');      // ultimo numero ya utilizado

            $table->string('tim_observacion', 150)->nullable();
            $table->smallInteger('tim_estado')->default(1);
            $table->timestamp('tim_creado')->useCurrent();

            $table->foreign('tim_empid', 'fk_tim_emp')
                  ->references('emp_id')->on('empresa')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('tim_tcoid', 'fk_tim_tco')
                  ->references('tco_id')->on('tipo_comprobante')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('tim_sucid', 'fk_tim_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(
                ['tim_empid', 'tim_numero', 'tim_tcoid', 'tim_establec', 'tim_puntexp'],
                'uq_tim_emp_num_tco_est_pex'
            );

            $table->index('tim_empid', 'ix_tim_empid');
            $table->index('tim_tcoid', 'ix_tim_tcoid');
            $table->index('tim_sucid', 'ix_tim_sucid');
        });

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_estado CHECK (tim_estado IN (0, 1))");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_numero CHECK (tim_numero ~ '^[0-9]{8}$')");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_establec CHECK (tim_establec ~ '^[0-9]{3}$')");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_puntexp CHECK (tim_puntexp ~ '^[0-9]{3}$')");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_vigencia CHECK (tim_fechfn >= tim_fechin)");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_rango CHECK (tim_nroini > 0 AND tim_nrofin >= tim_nroini)");

        DB::statement("ALTER TABLE timbrado
            ADD CONSTRAINT ck_tim_nroact
            CHECK (tim_nroact >= tim_nroini - 1 AND tim_nroact <= tim_nrofin)");

        DB::statement("CREATE UNIQUE INDEX uq_tim_activo_punto
            ON timbrado (tim_sucid, tim_tcoid, tim_establec, tim_puntexp)
            WHERE tim_estado = 1");
    }

    public function down(): void
    {
        Schema::dropIfExists('timbrado');
    }
};