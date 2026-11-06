<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos del servicio técnico. Espejo de pedido_pago (pago dividido +
 * verificación por pago) más lo que necesita el cobro presencial.
 *
 * Tabla espejo y no genérica a propósito: pedido_pago no se toca, así que
 * el checkout de pedidos no corre ningún riesgo.
 *
 * spa_tipo lo decide el service, nunca el cliente:
 *   ANTICIPO = cobrado en mostrador antes de LISTA (solo canal MOSTRADOR).
 *   TOTAL    = cubre todo sse_total en un solo pago, en LISTA.
 *   SALDO    = cualquier otro pago en LISTA (resto tras anticipo o pago dividido).
 *
 * spa_canal + spa_usucob + spa_sucid: en el mostrador siempre queda quién
 * cobró y en qué sucursal (ck_spa_mostrador). En la web el cliente declara y
 * el verificador queda en spa_usuver.
 *
 * spa_nrotrx guarda también la referencia del giro Tigo (BILLETERA_MOVIL).
 *
 * spa_sseid es RESTRICT (no CASCADE como en pedido_pago): un cobro es un
 * registro financiero y no debe desaparecer junto con la solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_pago', function (Blueprint $table) {
            $table->id('spa_id');
            $table->unsignedBigInteger('spa_sseid');
            $table->unsignedSmallInteger('spa_orden');          // 1 = "Pago 1", 2 = "Pago 2", ...
            $table->string('spa_tipo', 8);                      // ANTICIPO | SALDO | TOTAL
            $table->string('spa_canal', 10);                    // WEB | MOSTRADOR
            $table->unsignedSmallInteger('spa_mpaid');
            $table->decimal('spa_monto', 14, 2);
            $table->unsignedSmallInteger('spa_epaid');
            $table->string('spa_observ', 255)->nullable();      // motivo de rechazo / nota

            // Verificación (mismos campos que pedido_pago).
            $table->string('spa_nrotrx', 50)->nullable();       // nro. transacción o referencia del giro
            $table->string('spa_banco', 60)->nullable();
            $table->date('spa_fechpag')->nullable();
            $table->decimal('spa_montver', 14, 2)->nullable();
            $table->unsignedSmallInteger('spa_mrpid')->nullable();
            $table->unsignedInteger('spa_usuver')->nullable();
            $table->timestamp('spa_fechver')->nullable();

            // Cobro presencial.
            $table->unsignedInteger('spa_usucob')->nullable();  // funcionario que cobró
            $table->unsignedInteger('spa_sucid')->nullable();   // sucursal del cobro

            $table->timestamp('spa_creado')->useCurrent();

            $table->foreign('spa_sseid', 'fk_spa_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('spa_mpaid', 'fk_spa_mpa')->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('spa_epaid', 'fk_spa_epa')->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('spa_mrpid', 'fk_spa_mrp')->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('spa_usuver', 'fk_spa_usuver')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('spa_usucob', 'fk_spa_usucob')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('spa_sucid', 'fk_spa_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['spa_sseid', 'spa_orden'], 'uq_spa_sse_orden');
            $table->index('spa_epaid', 'ix_spa_estado');
            $table->index(['spa_sucid', 'spa_creado'], 'ix_spa_suc_fecha');
        });

        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_monto   CHECK (spa_monto > 0)");
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_montver CHECK (spa_montver IS NULL OR spa_montver > 0)");
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_tipo    CHECK (spa_tipo IN ('ANTICIPO', 'SALDO', 'TOTAL'))");
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_canal   CHECK (spa_canal IN ('WEB', 'MOSTRADOR'))");

        // Mostrador: siempre se sabe quién cobró y dónde.
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_mostrador
            CHECK (spa_canal <> 'MOSTRADOR' OR (spa_usucob IS NOT NULL AND spa_sucid IS NOT NULL))");

        // El anticipo existe únicamente en el mostrador.
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_anticipo
            CHECK (spa_tipo <> 'ANTICIPO' OR spa_canal = 'MOSTRADOR')");
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_pago');
    }
};
