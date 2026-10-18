<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El pedido pasa a nacer ANTES del pago (Paso 2 del checkout), por lo que
 * la reserva de stock queda tomada por un pedido que todavia no pago.
 *
 * ped_vence es el limite de esa reserva: si el cliente no declara su pago
 * antes de esa fecha, el pedido se cancela y sto_reserv se libera
 * (comando pedidos:expirar). Sin esta columna, un checkout abandonado
 * dejaria stock bloqueado de forma indefinida.
 *
 * Es nullable a proposito: los pedidos ya existentes (creados con el flujo
 * viejo, donde el pago venia antes) no tienen vencimiento y no deben
 * entrar nunca al barrido de expiracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->timestamp('ped_vence')->nullable()->after('ped_epaid');

            // El barrido de expiracion filtra por estado de pago + vencimiento.
            $table->index(['ped_epaid', 'ped_vence'], 'ix_ped_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->dropIndex('ix_ped_vencimiento');
            $table->dropColumn('ped_vence');
        });
    }
};
