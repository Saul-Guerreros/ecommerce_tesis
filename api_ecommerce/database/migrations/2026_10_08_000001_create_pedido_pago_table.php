<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos del pedido. Nace con el Paso 3 (Confirmar Pedido), igual que
 * pedido: PedidoService lee pedido_temporal.ptm_pago y crea una fila por
 * cada pago declarado por el cliente.
 *
 * Siempre se escribe, aunque el cliente haya elegido un solo metodo (1
 * fila). De esa forma el panel admin, "mis pedidos" y los reportes leen un
 * unico lugar, sin ramificar entre pedido.ped_mpaid y esta tabla.
 *
 * El estado es FK a estado_pago (PENDIENTE / CONFIRMADO / RECHAZADO) en
 * lugar de texto libre: el administrador verifica cada pago por separado,
 * asi que un pedido puede tener el Pago 1 confirmado y el Pago 2 rechazado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_pago', function (Blueprint $table) {
            $table->id('ppa_id');
            $table->unsignedBigInteger('ppa_pedid');
            $table->unsignedSmallInteger('ppa_orden');          // 1 = "Pago 1", 2 = "Pago 2", ...
            $table->unsignedSmallInteger('ppa_mpaid');
            $table->decimal('ppa_monto', 14, 2);
            $table->unsignedSmallInteger('ppa_epaid')->default(1);
            $table->string('ppa_observ', 255)->nullable();      // motivo de rechazo / nota del admin
            $table->timestamp('ppa_creado')->useCurrent();

            $table->foreign('ppa_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('ppa_mpaid')->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ppa_epaid')->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['ppa_pedid', 'ppa_orden'], 'uq_ppa_pedido_orden');
            $table->index('ppa_epaid', 'ix_ppa_estado');
        });

        // Tercera capa de la regla "el monto de cada pago debe ser mayor que 0".
        // Las otras dos son Angular (Validators.min) y Laravel (rule min:1).
        DB::statement('ALTER TABLE pedido_pago
            ADD CONSTRAINT ck_ppa_monto CHECK (ppa_monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_pago');
    }
};