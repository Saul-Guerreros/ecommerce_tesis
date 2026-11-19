<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cheque con el que se paga un medio de una declaracion de cuotas.
 *
 * Espejo reducido de pago_cheque. No se reutiliza esa tabla porque
 * pch_pedid es obligatoria y el cheque de una cuota no pertenece a
 * ningun pedido: nace contra la factura a credito, que ya se entrego.
 *
 * El ciclo del papel (declarado -> recibido -> acreditado) es el mismo,
 * asi que se conservan los mismos codigos de estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_cheque', function (Blueprint $table) {
            $table->id('cch_id');
            $table->unsignedBigInteger('cch_cpmid');

            $table->string('cch_tipo', 10);                 // VISTA | DIFERIDO
            $table->string('cch_banco', 80)->nullable();
            $table->string('cch_nrodoc', 20)->nullable();   // CI del titular
            $table->string('cch_nombre', 150)->nullable();
            $table->string('cch_nro', 20)->nullable();
            $table->date('cch_fecobr')->nullable();         // solo diferido

            $table->string('cch_estado', 15)->default('EN_ESPERA');
            $table->timestamp('cch_fevcto')->nullable();    // plazo de entrega
            $table->timestamp('cch_frecep')->nullable();
            $table->unsignedInteger('cch_usurec')->nullable();

            $table->unsignedSmallInteger('cch_mrpid')->nullable();
            $table->string('cch_motdet', 255)->nullable();

            $table->timestamp('cch_creado')->useCurrent();

            $table->foreign('cch_cpmid', 'fk_cch_cpm')
                  ->references('cpm_id')->on('cuota_pago_medio')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('cch_usurec', 'fk_cch_usurec')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->foreign('cch_mrpid', 'fk_cch_mrp')
                  ->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique('cch_cpmid', 'uq_cch_medio');
            $table->index('cch_estado', 'ix_cch_estado');
        });

        DB::statement("ALTER TABLE cuota_cheque ADD CONSTRAINT ck_cch_tipo
            CHECK (cch_tipo IN ('VISTA','DIFERIDO'))");

        DB::statement("ALTER TABLE cuota_cheque ADD CONSTRAINT ck_cch_estado
            CHECK (cch_estado IN ('EN_ESPERA','DESCARTADO','RECIBIDO','ACREDITADO','NO_ENTREGADO','RECHAZADO'))");

        // El diferido es el unico que lleva fecha de cobro.
        DB::statement("ALTER TABLE cuota_cheque ADD CONSTRAINT ck_cch_fecobr
            CHECK (cch_tipo = 'DIFERIDO' OR cch_fecobr IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_cheque');
    }
};