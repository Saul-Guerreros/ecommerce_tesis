<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El comprobante deja de ser "uno por pedido" y pasa a ser "uno por pago".
 *
 * Se reutiliza pago_comprobante en lugar de agregar una columna
 * pedido_pago.ppa_comprobante porque esta tabla ya tiene pco_verifi y
 * pco_subido, que son justamente lo que el Modulo 4 (confirmacion de
 * cobro) necesita. Duplicar la ruta del archivo en dos tablas abriria la
 * puerta a que se desincronicen.
 *
 * pco_ppaid queda nullable para no invalidar los comprobantes de pedidos
 * creados antes de esta migracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_comprobante', function (Blueprint $table) {
            $table->unsignedBigInteger('pco_ppaid')->nullable()->after('pco_pedid');

            $table->foreign('pco_ppaid')->references('ppa_id')->on('pedido_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->index('pco_ppaid', 'ix_pco_pago');
        });
    }

    public function down(): void
    {
        Schema::table('pago_comprobante', function (Blueprint $table) {
            $table->dropForeign(['pco_ppaid']);
            $table->dropIndex('ix_pco_pago');
            $table->dropColumn('pco_ppaid');
        });
    }
};