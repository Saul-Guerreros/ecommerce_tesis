<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Cabecera de la solicitud de servicio técnico (espejo de `pedido`).
 *
 * Cuatro ejes de estado independientes:
 *   sse_etsid  -> estado_servicio   (ciclo técnico)
 *   sse_elging -> estado_logistica  (ámbito INGRESO)
 *   sse_epaid  -> estado_pago       (cobro; NULL hasta que la OT se cierra)
 *   sse_elgegr -> estado_logistica  (ámbito EGRESO)
 *
 * Modalidad de ingreso (sse_tenid -> tipo_entrega):
 *   RETIRO = el cliente lleva el equipo a la sucursal (sse_sucid)
 *   ENVIO  = el taller lo retira del domicilio (sse_dirid + sse_cosret)
 *
 * Columnas agregadas al brief:
 *   sse_acceso: accesorios que el CLIENTE declara en el wizard. No es la
 *               constancia legal (esa es recepcion_accesorio): sirve para
 *               precargar el checklist de A2.
 *   sse_fecing: fecha prevista de ingreso (llevo al local) o de retiro
 *               agendado (a domicilio).
 *
 * sse_numero nullable: se completa en la misma transacción con el id
 * real, igual que ped_numero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_servicio', function (Blueprint $table) {
            $table->id('sse_id');
            $table->string('sse_numero', 20)->nullable();
            $table->unsignedBigInteger('sse_cliid');

            // Equipo
            $table->unsignedSmallInteger('sse_tdiid');
            $table->string('sse_marca', 50);
            $table->string('sse_modelo', 80);
            $table->string('sse_nroser', 80)->nullable();
            $table->string('sse_proble', 500);
            $table->string('sse_acceso', 255)->nullable();

            // Garantía
            $table->boolean('sse_garant')->default(false);
            $table->unsignedBigInteger('sse_pedid')->nullable();
            $table->unsignedBigInteger('sse_sseori')->nullable();

            // Ingreso
            $table->unsignedSmallInteger('sse_tenid');
            $table->unsignedInteger('sse_sucid')->nullable();
            $table->unsignedInteger('sse_dirid')->nullable();
            $table->date('sse_fecing')->nullable();
            $table->decimal('sse_cosret', 14, 2)->default(0);

            // Ejes de estado
            $table->unsignedSmallInteger('sse_etsid');
            $table->unsignedSmallInteger('sse_elging');
            $table->unsignedSmallInteger('sse_epaid')->nullable();
            $table->unsignedSmallInteger('sse_elgegr');

            // Totales (se consolidan al cerrar la OT)
            $table->decimal('sse_subtot', 14, 2)->default(0);
            $table->decimal('sse_impues', 14, 2)->default(0);
            $table->decimal('sse_cosenv', 14, 2)->default(0);
            $table->decimal('sse_total', 14, 2)->default(0);

            $table->timestamp('sse_creado')->useCurrent();
            $table->timestamp('sse_actual')->nullable();

            $table->unique('sse_numero', 'uq_sse_numero');

            $table->foreign('sse_cliid', 'fk_sse_cli')->references('cli_id')->on('cliente')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_tdiid', 'fk_sse_tdi')->references('tdi_id')->on('tipo_dispositivo')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_pedid', 'fk_sse_ped')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_sseori', 'fk_sse_sseori')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_tenid', 'fk_sse_ten')->references('ten_id')->on('tipo_entrega')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_sucid', 'fk_sse_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_dirid', 'fk_sse_dir')->references('dir_id')->on('direccion')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_etsid', 'fk_sse_ets')->references('ets_id')->on('estado_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_elging', 'fk_sse_elging')->references('elg_id')->on('estado_logistica')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_epaid', 'fk_sse_epa')->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sse_elgegr', 'fk_sse_elgegr')->references('elg_id')->on('estado_logistica')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('sse_cliid', 'ix_sse_cliid');
            $table->index('sse_etsid', 'ix_sse_etsid');
            $table->index('sse_elging', 'ix_sse_elging');
            $table->index('sse_creado', 'ix_sse_creado');
        });

        DB::statement("ALTER TABLE solicitud_servicio ADD CONSTRAINT ck_sse_ingreso
            CHECK (sse_sucid IS NOT NULL OR sse_dirid IS NOT NULL)");
        DB::statement("ALTER TABLE solicitud_servicio ADD CONSTRAINT ck_sse_garant
            CHECK (sse_garant = false OR sse_pedid IS NOT NULL OR sse_sseori IS NOT NULL)");
        DB::statement("ALTER TABLE solicitud_servicio ADD CONSTRAINT ck_sse_montos
            CHECK (sse_cosret >= 0 AND sse_subtot >= 0 AND sse_impues >= 0 AND sse_cosenv >= 0 AND sse_total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_servicio');
    }
};
