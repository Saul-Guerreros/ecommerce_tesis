<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Intencion de pago de UNA cuota. Espejo de servicio_pago.
 *
 * No se reutiliza pedido_pago a proposito: esa tabla pertenece al
 * checkout (ppa_orden unico por pedido, el panel suma sus filas para el
 * progreso de pago y PedidoEstadoService la recorre para decidir
 * transiciones). Meter ahi las cuotas haria que un pedido ya entregado
 * volviera a moverse de estado con cada cobro.
 *
 * Es una DECLARACION del cliente, no un hecho: el hecho es cobro_venta,
 * que registra el admin al validar. Mismo par declaracion/hecho que ya
 * existe entre pedido_pago y cobro_venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_pago', function (Blueprint $table) {
            $table->id('cpa_id');
            $table->unsignedBigInteger('cpa_ccoid');            // cuota declarada

            $table->unsignedSmallInteger('cpa_mpaid');
            $table->decimal('cpa_monto', 14, 2);                // = cco_monto (sin parciales)
            $table->unsignedSmallInteger('cpa_epaid');          // estado_pago

            $table->string('cpa_nrotrx', 50)->nullable();
            $table->string('cpa_banco', 60)->nullable();
            $table->date('cpa_fechpag')->nullable();            // fecha que declara el cliente

            $table->string('cpa_observ', 255)->nullable();      // motivo de rechazo / nota
            $table->unsignedSmallInteger('cpa_mrpid')->nullable();
            $table->unsignedInteger('cpa_usuver')->nullable();
            $table->timestamp('cpa_fechver')->nullable();

            $table->timestamp('cpa_creado')->useCurrent();

            $table->foreign('cpa_ccoid', 'fk_cpa_cco')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpa_mpaid', 'fk_cpa_mpa')
                  ->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpa_epaid', 'fk_cpa_epa')
                  ->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpa_mrpid', 'fk_cpa_mrp')
                  ->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpa_usuver', 'fk_cpa_usuver')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index('cpa_ccoid', 'ix_cpa_cuota');
            $table->index('cpa_epaid', 'ix_cpa_estado');
        });

        DB::statement('ALTER TABLE cuota_pago ADD CONSTRAINT ck_cpa_monto CHECK (cpa_monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_pago');
    }
};