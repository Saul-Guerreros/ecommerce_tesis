<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de la Nota de Crédito de venta.
 *
 * Diferencia de fondo con nota_credito (compras): aquella TRANSCRIBE un
 * documento que el proveedor ya emitió. Esta EMITE un documento fiscal
 * propio, con nuestro timbrado y nuestra numeración. Por eso repite el
 * mismo bloque de snapshot que venta_cab: timbrado, vigencia, emisor,
 * establecimiento, punto de expedición y número.
 *
 * El adquirente se copia de la FACTURA, no del cliente: la NC tiene que
 * nombrar al mismo adquirente que se declaró, aunque el cliente haya
 * cambiado su razón social después.
 *
 * ncv_depid guarda el depósito donde reingresó la mercadería. Es NULL en
 * los motivos que no mueven stock (descuento, error de precio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_credito_venta', function (Blueprint $table) {
            $table->increments('ncv_id');

            // --- Relaciones ---
            $table->unsignedInteger('ncv_venid');              // factura corregida
            $table->unsignedInteger('ncv_timid');              // talonario de NC usado
            $table->unsignedInteger('ncv_cajid');              // punto de expedición
            $table->unsignedInteger('ncv_cliid');
            $table->unsignedInteger('ncv_usuid');              // quien emite
            $table->unsignedInteger('ncv_sucid');
            $table->unsignedInteger('ncv_mncid');              // motivo
            $table->unsignedInteger('ncv_depid')->nullable();  // depósito del reingreso

            // --- Identificación del documento (3-3-7) ---
            $table->char('ncv_timbrado', 8);
            $table->date('ncv_timb_inicio');
            $table->date('ncv_timb_venc');
            $table->string('ncv_emp_ruc', 15);
            $table->string('ncv_emp_razon', 45);
            $table->char('ncv_establec', 3);
            $table->char('ncv_puntoexp', 3);
            $table->integer('ncv_numero');
            $table->date('ncv_fecha');

            // --- Adquirente (copiado de la factura) ---
            $table->string('ncv_razonsocial', 150);
            $table->string('ncv_ruc', 15)->nullable();
            $table->string('ncv_ci', 15)->nullable();

            // --- Importes acreditados (mismo criterio que venta_cab) ---
            $table->decimal('ncv_exenta', 14, 2)->default(0);
            $table->decimal('ncv_grav5', 14, 2)->default(0);
            $table->decimal('ncv_grav10', 14, 2)->default(0);
            $table->decimal('ncv_subtotal', 14, 2)->default(0);
            $table->decimal('ncv_total', 14, 2)->default(0);

            // --- Liquidación del IVA (por dentro) ---
            $table->decimal('ncv_iva5', 14, 2)->default(0);
            $table->decimal('ncv_iva10', 14, 2)->default(0);
            $table->decimal('ncv_totiva', 14, 2)->default(0);

            // --- Control ---
            $table->smallInteger('ncv_estado')->default(1);    // 1=Emitida, 9=Anulada
            $table->string('ncv_obs', 255)->nullable();
            $table->timestamp('ncv_creado')->useCurrent();
            $table->timestamp('ncv_anulado')->nullable();
            $table->unsignedInteger('ncv_anusid')->nullable();
            $table->string('ncv_motanu', 255)->nullable();

            $table->foreign('ncv_venid', 'fk_ncv_ven')->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_timid', 'fk_ncv_tim')->references('tim_id')->on('timbrado')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_cajid', 'fk_ncv_caj')->references('caj_id')->on('caja')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_cliid', 'fk_ncv_cli')->references('cli_id')->on('cliente')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_usuid', 'fk_ncv_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_sucid', 'fk_ncv_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_mncid', 'fk_ncv_mnc')->references('mnc_id')->on('motivo_nota_credito')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_depid', 'fk_ncv_dep')->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ncv_anusid', 'fk_ncv_anusid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            // Dos NC no comparten número dentro del mismo talonario.
            $table->unique(['ncv_timid', 'ncv_numero'], 'uq_ncv_doc');

            $table->index('ncv_venid', 'ix_ncv_venta');
            $table->index('ncv_fecha', 'ix_ncv_fecha');
            $table->index(['ncv_cliid', 'ncv_fecha'], 'ix_ncv_cliente');
            $table->index('ncv_estado', 'ix_ncv_estado');
        });

        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_numero CHECK (ncv_numero > 0)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_ident
            CHECK (ncv_ruc IS NOT NULL OR ncv_ci IS NOT NULL)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_timb_vigencia
            CHECK (ncv_timb_venc >= ncv_timb_inicio)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_subtotal
            CHECK (ncv_subtotal = ncv_exenta + ncv_grav5 + ncv_grav10)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_totiva
            CHECK (ncv_totiva = ncv_iva5 + ncv_iva10)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_total
            CHECK (ncv_total = ncv_subtotal AND ncv_total > 0)');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_estado
            CHECK (ncv_estado IN (1, 9))');
        DB::statement('ALTER TABLE nota_credito_venta ADD CONSTRAINT ck_ncv_anulado
            CHECK ((ncv_estado = 9) = (ncv_anulado IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_venta');
    }
};