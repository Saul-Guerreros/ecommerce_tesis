<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro de una factura de venta. Espejo de pago_compra (lado compras).
 *
 * Cuelga de la FACTURA (cob_venid), no del pedido: asi, cuando se
 * implementen las ventas a credito y el cobro de servicios tecnicos, esta
 * tabla no se toca (solo se agregan cuota_venta y detalle_cobro_venta).
 *
 * cob_ppaid enlaza con la intencion de pago del cliente (pedido_pago) y es
 * nullable justamente por esos otros origenes futuros que no pasan por un
 * pedido de la tienda.
 *
 * Un cobro no se edita ni se borra: se anula (cob_estado = 0) dejando
 * fecha, usuario y motivo, igual que pago_compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobro_venta', function (Blueprint $table) {
            $table->increments('cob_id');

            $table->unsignedInteger('cob_venid');                 // factura cobrada
            $table->unsignedSmallInteger('cob_mpaid');            // metodo de pago
            $table->decimal('cob_monto', 14, 2);                  // monto verificado
            $table->string('cob_nrotrx', 50)->nullable();
            $table->string('cob_banco', 60)->nullable();
            $table->date('cob_fecha');
            $table->string('cob_observ', 255)->nullable();

            $table->unsignedBigInteger('cob_ppaid')->nullable();  // intencion de pago del cliente
            $table->unsignedInteger('cob_usuid');                 // quien registro el cobro

            $table->smallInteger('cob_estado')->default(1);       // 1 activo, 0 anulado
            $table->timestamp('cob_fecanu')->nullable();
            $table->unsignedInteger('cob_anusid')->nullable();
            $table->string('cob_motanu', 255)->nullable();        // por que se anulo
            $table->timestamp('cob_creado')->useCurrent();

            $table->foreign('cob_venid', 'fk_cob_ven')->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('cob_mpaid', 'fk_cob_mpa')->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('cob_ppaid', 'fk_cob_ppa')->references('ppa_id')->on('pedido_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('cob_usuid', 'fk_cob_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('cob_anusid', 'fk_cob_anusid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index('cob_venid', 'ix_cob_venta');
            $table->index('cob_fecha', 'ix_cob_fecha');
        });

        DB::statement('ALTER TABLE cobro_venta ADD CONSTRAINT ck_cob_monto CHECK (cob_monto > 0)');
        DB::statement('ALTER TABLE cobro_venta ADD CONSTRAINT ck_cob_estado CHECK (cob_estado IN (0, 1))');
        DB::statement('ALTER TABLE cobro_venta ADD CONSTRAINT ck_cob_anulado
            CHECK ((cob_estado = 0) = (cob_fecanu IS NOT NULL))');

        // Una intencion de pago (pedido_pago) tiene como maximo UN cobro
        // activo: impide cobrar dos veces el mismo pago. Anulado el cobro,
        // el pago puede volver a cobrarse.
        DB::statement('CREATE UNIQUE INDEX uq_cob_ppa_activo
            ON cobro_venta (cob_ppaid) WHERE cob_ppaid IS NOT NULL AND cob_estado = 1');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_cob_ppa_activo');
        Schema::dropIfExists('cobro_venta');
    }
};