<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ped_mpaid pasa a ser el metodo "principal" y solo tiene sentido cuando
 * el pedido se pago con un unico metodo. Con pago dividido queda en NULL y
 * la verdad esta en pedido_pago.
 *
 * ped_pagdiv es el discriminador: evita que cada consulta tenga que contar
 * filas de pedido_pago para saber como mostrar el pedido.
 *
 * El DROP NOT NULL se hace con SQL plano a proposito: ->change() de Laravel
 * 10 exige instalar doctrine/dbal, dependencia que no aporta nada mas al
 * proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->boolean('ped_pagdiv')->default(false)->after('ped_mpaid');
        });

        DB::statement('ALTER TABLE pedido ALTER COLUMN ped_mpaid DROP NOT NULL');
    }

    public function down(): void
    {
        // Los pedidos divididos quedan sin metodo; se les asigna el del pago
        // de mayor monto para poder restaurar el NOT NULL sin perder la fila.
        DB::statement("
            UPDATE pedido p
               SET ped_mpaid = sub.ppa_mpaid
              FROM (
                    SELECT DISTINCT ON (ppa_pedid) ppa_pedid, ppa_mpaid
                      FROM pedido_pago
                     ORDER BY ppa_pedid, ppa_monto DESC, ppa_orden
                   ) sub
             WHERE p.ped_id = sub.ppa_pedid
               AND p.ped_mpaid IS NULL
        ");

        DB::statement('ALTER TABLE pedido ALTER COLUMN ped_mpaid SET NOT NULL');

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropColumn('ped_pagdiv');
        });
    }
};