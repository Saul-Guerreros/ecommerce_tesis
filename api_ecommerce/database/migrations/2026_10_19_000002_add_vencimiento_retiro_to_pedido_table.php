<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazo para retirar el pedido en sucursal.
 *
 * Es una columna separada de ped_vence y no una reutilización de aquélla,
 * porque son dos relojes distintos que miden cosas distintas y nunca
 * corren a la vez:
 *
 *   - ped_vence:   plazo para PAGAR. Arranca al confirmar el pedido y se
 *                  cierra (NULL) cuando el cliente declara su pago.
 *   - ped_vencret: plazo para RETIRAR. Arranca recién al pasar a LISTO.
 *
 * El reloj del retiro no puede arrancar antes: el cliente no puede
 * retirar algo que todavía no está armado. Si se contara desde la
 * confirmación, el tiempo que la tienda tarda en preparar se le
 * descontaría al cliente — con 5 días de plazo y 3 de preparación, le
 * quedarían 2.
 *
 * Nullable porque sólo aplica a los pedidos con entrega RETIRO: en un
 * envío a domicilio no hay nada que el cliente tenga que ir a buscar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->timestamp('ped_vencret')->nullable()->after('ped_vence');
        });
    }

    public function down(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->dropColumn('ped_vencret');
        });
    }
};