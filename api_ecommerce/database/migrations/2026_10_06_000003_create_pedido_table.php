<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nace recien en el Paso 3 (Confirmar Pedido): lee pedido_temporal y crea
 * esta fila + detalle_pedido + pedido_entrega + pedido_facturacion en una
 * sola transaccion (PedidoService::confirmar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido', function (Blueprint $table) {
            $table->id('ped_id');
            $table->string('ped_numero', 20)->nullable();
            $table->unsignedInteger('ped_cliid');
            $table->decimal('ped_subtot', 14, 2);
            $table->decimal('ped_impues', 14, 2)->default(0);
            $table->decimal('ped_cosenv', 14, 2)->default(0);
            $table->decimal('ped_total', 14, 2);
            $table->unsignedSmallInteger('ped_tenid');
            $table->unsignedInteger('ped_sucid')->nullable();
            $table->unsignedSmallInteger('ped_mpaid');
            $table->unsignedSmallInteger('ped_estid')->default(1);
            $table->unsignedSmallInteger('ped_epaid')->default(1);
            $table->string('ped_obsint', 500)->nullable();
            $table->timestamp('ped_creado')->useCurrent();
            $table->timestamp('ped_actual')->nullable();

            $table->foreign('ped_cliid')->references('cli_id')->on('cliente')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ped_tenid')->references('ten_id')->on('tipo_entrega')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ped_sucid')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ped_mpaid')->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ped_estid')->references('est_id')->on('estado_pedido')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ped_epaid')->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique('ped_numero', 'uq_ped_numero');
            $table->index(['ped_cliid', 'ped_creado'], 'ix_ped_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido');
    }
};