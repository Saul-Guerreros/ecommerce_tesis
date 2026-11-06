<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostico_preliminar', function (Blueprint $table) {
            $table->id('dpr_id');
            $table->unsignedBigInteger('dpr_sseid')->nullable();   // se vincula al registrar la solicitud
            $table->unsignedInteger('dpr_usuid');                  // cliente (WEB) o funcionario (MOSTRADOR)
            $table->string('dpr_canal', 10);
            $table->unsignedSmallInteger('dpr_tdiid');
            $table->string('dpr_marca', 50);
            $table->string('dpr_modelo', 80);
            $table->string('dpr_proble', 500);
            $table->boolean('dpr_garant')->default(false);
            $table->string('dpr_estado', 13);
            $table->string('dpr_resume', 500)->nullable();
            $table->string('dpr_confia', 5)->nullable();
            $table->jsonb('dpr_pregun')->nullable();
            $table->decimal('dpr_cosmin', 14, 2)->nullable();
            $table->decimal('dpr_cosmax', 14, 2)->nullable();
            $table->smallInteger('dpr_dias')->nullable();
            $table->string('dpr_prmver', 10);                      // versión del prompt
            $table->unsignedInteger('dpr_latms')->nullable();
            $table->string('dpr_coinci', 7)->nullable();           // se completa con el diagnóstico del técnico
            $table->timestamp('dpr_evalua')->nullable();
            $table->timestamp('dpr_creado')->useCurrent();

            $table->unique('dpr_sseid', 'uq_dpr_sseid');
            $table->foreign('dpr_sseid', 'fk_dpr_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('dpr_usuid', 'fk_dpr_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dpr_tdiid', 'fk_dpr_tdi')->references('tdi_id')->on('tipo_dispositivo')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->index('dpr_creado', 'ix_dpr_creado');
        });

        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_canal CHECK (dpr_canal IN ('WEB', 'MOSTRADOR'))");
        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_estado CHECK (dpr_estado IN ('GENERADO', 'NO_DISPONIBLE'))");
        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_confia CHECK (dpr_confia IS NULL OR dpr_confia IN ('ALTA', 'MEDIA', 'BAJA'))");
        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_coinci CHECK (dpr_coinci IS NULL OR dpr_coinci IN ('TOTAL', 'PARCIAL', 'NINGUNA'))");
        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_costos CHECK (dpr_cosmin IS NULL OR dpr_cosmax >= dpr_cosmin)");
        // Garantía: nunca lleva estimación de costo ni plazo.
        DB::statement("ALTER TABLE diagnostico_preliminar ADD CONSTRAINT ck_dpr_garant
            CHECK (dpr_garant = false OR (dpr_cosmin IS NULL AND dpr_cosmax IS NULL AND dpr_dias IS NULL))");

        Schema::create('diagnostico_preliminar_falla', function (Blueprint $table) {
            $table->id('dpf_id');
            $table->unsignedBigInteger('dpf_dprid');
            $table->unsignedInteger('dpf_fllid');
            $table->string('dpf_probab', 5);
            $table->smallInteger('dpf_orden');

            $table->unique(['dpf_dprid', 'dpf_fllid'], 'uq_dpf_dpr_fll');
            $table->foreign('dpf_dprid', 'fk_dpf_dpr')->references('dpr_id')->on('diagnostico_preliminar')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dpf_fllid', 'fk_dpf_fll')->references('fll_id')->on('falla')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
        DB::statement("ALTER TABLE diagnostico_preliminar_falla ADD CONSTRAINT ck_dpf_probab CHECK (dpf_probab IN ('ALTA', 'MEDIA', 'BAJA'))");

        Schema::create('diagnostico_preliminar_trabajo', function (Blueprint $table) {
            $table->id('dpt_id');
            $table->unsignedBigInteger('dpt_dprid');
            $table->unsignedInteger('dpt_trbid');
            $table->decimal('dpt_precio', 14, 2)->nullable();      // snapshot; NULL en garantía
            $table->smallInteger('dpt_dias')->nullable();

            $table->unique(['dpt_dprid', 'dpt_trbid'], 'uq_dpt_dpr_trb');
            $table->foreign('dpt_dprid', 'fk_dpt_dpr')->references('dpr_id')->on('diagnostico_preliminar')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dpt_trbid', 'fk_dpt_trb')->references('trb_id')->on('trabajo')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostico_preliminar_trabajo');
        Schema::dropIfExists('diagnostico_preliminar_falla');
        Schema::dropIfExists('diagnostico_preliminar');
    }
};