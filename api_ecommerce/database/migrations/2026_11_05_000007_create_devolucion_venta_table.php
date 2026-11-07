<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devolución EFECTIVA de dinero al cliente (espejo de cobro_venta, en sentido
 * inverso). La nota de crédito corrige el documento fiscal; esto registra que
 * el dinero salió de la caja o de la cuenta (guía 0.54.1, cap. 30).
 *
 * Cuelga de la NC que reconoce el crédito: no hay devolución sin NC. La suma
 * de devoluciones activas no supera ncv_total (lo controla el service).
 * No se edita ni se borra: se anula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_venta', function (Blueprint $table) {
            $table->increments('dev_id');

            $table->unsignedInteger('dev_ncvid');                 // NC que la respalda
            $table->unsignedSmallInteger('dev_mpaid');            // medio de la devolución
            $table->decimal('dev_monto', 14, 2);
            $table->string('dev_nrotrx', 50)->nullable();
            $table->string('dev_banco', 60)->nullable();
            $table->date('dev_fecha');
            $table->string('dev_observ', 255)->nullable();

            $table->unsignedInteger('dev_usuid');                 // quien la registró
            $table->unsignedInteger('dev_sucid');                 // sucursal / caja

            $table->smallInteger('dev_estado')->default(1);       // 1 activa, 0 anulada
            $table->timestamp('dev_fecanu')->nullable();
            $table->unsignedInteger('dev_anusid')->nullable();
            $table->string('dev_motanu', 255)->nullable();
            $table->timestamp('dev_creado')->useCurrent();

            $table->foreign('dev_ncvid', 'fk_dev_ncv')->references('ncv_id')->on('nota_credito_venta')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dev_mpaid', 'fk_dev_mpa')->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dev_usuid', 'fk_dev_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dev_sucid', 'fk_dev_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('dev_anusid', 'fk_dev_anusid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index('dev_ncvid', 'ix_dev_nota');
            $table->index('dev_fecha', 'ix_dev_fecha');
        });

        DB::statement('ALTER TABLE devolucion_venta ADD CONSTRAINT ck_dev_monto CHECK (dev_monto > 0)');
        DB::statement('ALTER TABLE devolucion_venta ADD CONSTRAINT ck_dev_estado CHECK (dev_estado IN (0, 1))');
        DB::statement('ALTER TABLE devolucion_venta ADD CONSTRAINT ck_dev_anulado
            CHECK ((dev_estado = 0) = (dev_fecanu IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_venta');
    }
};