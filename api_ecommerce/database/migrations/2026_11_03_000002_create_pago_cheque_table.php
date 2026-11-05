<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del cheque declarado por el cliente para UN pago concreto.
 *
 * Es 1:1 con pedido_pago, exactamente como pago_comprobante: con pago
 * dividido el cliente puede entregar dos cheques distintos, así que los
 * datos no pueden vivir en pedido.
 *
 * Por qué una tabla y no columnas en pedido_pago: mantiene esa tabla
 * limpia (un pago es monto + método + estado) y evita cuatro columnas que
 * quedarían en NULL en todos los pagos que no son cheque, que son la
 * mayoría.
 *
 * pch_pedid es redundante respecto de pedido_pago.ppa_pedid, pero se
 * mantiene por consistencia con pago_comprobante y para que la bandeja
 * del admin pueda filtrar cheques por pedido sin un JOIN extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_cheque', function (Blueprint $table) {
            $table->id('pch_id');
            $table->unsignedBigInteger('pch_pedid');
            $table->unsignedBigInteger('pch_ppaid');

            $table->string('pch_tipo', 10);      // VISTA | DIFERIDO
            $table->string('pch_banco', 80);     // Nombre del banco
            $table->string('pch_nrodoc', 20);    // CI del titular
            $table->string('pch_nombre', 150);   // Nombre del titular

            $table->timestamp('pch_creado')->useCurrent();

            $table->foreign('pch_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pch_ppaid')->references('ppa_id')->on('pedido_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            // Un cheque por pago: es lo que hace que la relación sea HasOne
            // y no HasMany, igual que el comprobante.
            $table->unique('pch_ppaid', 'uq_pch_pago');
            $table->index('pch_pedid', 'ix_pch_pedido');
        });

        // Tercera capa de la regla "el tipo sólo puede ser VISTA o
        // DIFERIDO". Las otras dos son Angular (select) y Laravel
        // (Rule::in sobre los tipos habilitados por el admin).
        DB::statement("ALTER TABLE pago_cheque
            ADD CONSTRAINT ck_pch_tipo CHECK (pch_tipo IN ('VISTA', 'DIFERIDO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_cheque');
    }
};