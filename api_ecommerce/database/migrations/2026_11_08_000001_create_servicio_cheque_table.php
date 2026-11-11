// api_ecommerce/database/migrations/2026_11_08_000001_create_servicio_cheque_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cheque del servicio técnico. Espejo de pago_cheque (pedidos), igual que
 * servicio_comprobante lo es de pago_comprobante:
 *
 *   servicio_cheque         1:1 con servicio_pago (un cheque por pago).
 *   servicio_cheque_cambio  foto de los datos antes de [Editar]/[Descartar].
 *
 * Los estados y tipos son los mismos códigos de PagoCheque: una sola
 * fuente de verdad para el ciclo del cheque en todo el sistema.
 *
 * sch_banco/nombre/nrodoc/nro son NULL-ables por el [Descartar], que borra
 * lo declarado mientras se esperan los datos del papel real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_cheque', function (Blueprint $table) {
            $table->id('sch_id');
            $table->unsignedBigInteger('sch_spaid');

            $table->string('sch_tipo', 10);                     // VISTA | DIFERIDO
            $table->string('sch_banco', 80)->nullable();
            $table->string('sch_nrodoc', 20)->nullable();       // CI del titular
            $table->string('sch_nombre', 150)->nullable();      // nombre del titular
            $table->string('sch_nro', 20)->nullable();          // número del cheque
            $table->date('sch_fecobr')->nullable();             // solo diferido

            $table->string('sch_estado', 15)->default('EN_ESPERA');
            $table->timestamp('sch_fevcto')->nullable();        // plazo de entrega (48 h)
            $table->timestamp('sch_frecep')->nullable();
            $table->unsignedInteger('sch_usurec')->nullable();
            $table->unsignedSmallInteger('sch_mrpid')->nullable();
            $table->string('sch_motdet', 300)->nullable();

            $table->timestamp('sch_creado')->useCurrent();

            $table->foreign('sch_spaid', 'fk_sch_spa')->references('spa_id')->on('servicio_pago')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('sch_usurec', 'fk_sch_usurec')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('sch_mrpid', 'fk_sch_mrp')->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique('sch_spaid', 'uq_sch_pago');
            $table->index(['sch_estado', 'sch_fevcto'], 'ix_sch_estado_vence');
        });

        DB::statement("ALTER TABLE servicio_cheque
            ADD CONSTRAINT ck_sch_tipo CHECK (sch_tipo IN ('VISTA', 'DIFERIDO'))");
        DB::statement("ALTER TABLE servicio_cheque
            ADD CONSTRAINT ck_sch_estado CHECK (sch_estado IN
                ('EN_ESPERA', 'DESCARTADO', 'RECIBIDO', 'ACREDITADO', 'NO_ENTREGADO', 'RECHAZADO'))");

        Schema::create('servicio_cheque_cambio', function (Blueprint $table) {
            $table->id('scc_id');
            $table->unsignedBigInteger('scc_schid');
            $table->string('scc_tipo', 10);                     // DESCARTE | EDICION
            $table->string('scc_tipche', 10);
            $table->string('scc_banco', 80)->nullable();
            $table->string('scc_nrodoc', 20)->nullable();
            $table->string('scc_nombre', 150)->nullable();
            $table->string('scc_nro', 20)->nullable();
            $table->date('scc_fecobr')->nullable();
            $table->string('scc_motivo', 300)->nullable();
            $table->unsignedInteger('scc_usuid')->nullable();
            $table->timestamp('scc_fecha')->useCurrent();

            $table->foreign('scc_schid', 'fk_scc_sch')->references('sch_id')->on('servicio_cheque')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('scc_usuid', 'fk_scc_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement("ALTER TABLE servicio_cheque_cambio
            ADD CONSTRAINT ck_scc_tipo CHECK (scc_tipo IN ('DESCARTE', 'EDICION'))");

        // Eje CHEQUE del historial (igual que peh_tipo = 'CHEQUE' en pedidos).
        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_tipo');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_tipo
            CHECK (she_tipo IN ('SERVICIO', 'INGRESO', 'EGRESO', 'PAGO', 'PRESUPUESTO', 'NOTA', 'CONTACTO', 'CHEQUE'))");

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_estid');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_estid
            CHECK (she_tipo IN ('NOTA', 'CONTACTO', 'CHEQUE') OR she_estid IS NOT NULL)");
    }

    public function down(): void
    {
        DB::table('servicio_historial_estado')->where('she_tipo', 'CHEQUE')->delete();

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_estid');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_estid
            CHECK (she_tipo IN ('NOTA', 'CONTACTO') OR she_estid IS NOT NULL)");

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_tipo');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_tipo
            CHECK (she_tipo IN ('SERVICIO', 'INGRESO', 'EGRESO', 'PAGO', 'PRESUPUESTO', 'NOTA', 'CONTACTO'))");

        Schema::dropIfExists('servicio_cheque_cambio');
        Schema::dropIfExists('servicio_cheque');
    }
};