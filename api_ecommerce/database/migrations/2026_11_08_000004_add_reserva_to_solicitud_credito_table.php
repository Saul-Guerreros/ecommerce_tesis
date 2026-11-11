<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de stock de la solicitud de credito.
 *
 * La solicitud reserva (sto_reserv) desde que el cliente la genera, no
 * desde que el administrador la aprueba: entre esos dos momentos pasan
 * dias, y evaluar una solicitud de un producto que mientras tanto se
 * agoto es trabajo perdido y una mala noticia tardia para el cliente.
 *
 * scr_depid guarda EN QUE DEPOSITO se reservo, igual que el pedido
 * resuelve el suyo al confirmar. No se recalcula al liberar: si mañana
 * cambia cual es la sucursal principal, liberar por recalculo devolveria
 * las unidades a un deposito distinto del que las aparto, y el stock de
 * los dos quedaria mal.
 *
 * scr_fecres hace la liberacion idempotente: se pone en NULL al
 * liberar, asi una segunda corrida del barrido no descuenta dos veces la
 * misma reserva. Es el equivalente de yaDescontado() en
 * StockPedidoService, resuelto con una marca porque acá no hay kardex
 * (la reserva nunca genera movimiento de stock).
 *
 * Las solicitudes anteriores a esta migracion quedan con scr_depid y
 * scr_fecres en NULL: nunca reservaron nada y liberar sobre ellas no
 * hace nada, que es exactamente lo correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_credito', function (Blueprint $table) {
            $table->unsignedBigInteger('scr_depid')->nullable()->after('scr_declar');
            $table->timestamp('scr_fecres')->nullable()->after('scr_depid');

            $table->foreign('scr_depid', 'fk_scr_depid')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            // El barrido de ofertas vencidas filtra por reserva activa.
            $table->index('scr_fecres', 'ix_scr_fecres');
        });

        // Una reserva activa necesita saber en que deposito esta.
        DB::statement('ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_reserva
            CHECK (scr_fecres IS NULL OR scr_depid IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitud_credito DROP CONSTRAINT IF EXISTS ck_scr_reserva');

        Schema::table('solicitud_credito', function (Blueprint $table) {
            $table->dropForeign('fk_scr_depid');
            $table->dropIndex('ix_scr_fecres');
            $table->dropColumn(['scr_depid', 'scr_fecres']);
        });
    }
};