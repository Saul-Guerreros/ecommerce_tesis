<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrador del checkout: una fila por usuario, sobrescrita en cada paso.
 * pedido.ped_mpaid, ped_numero y ped_total no pueden resolverse hasta que
 * el Paso 2 (metodo de pago) y el Paso 3 cierren, asi que pedido no puede
 * nacer todavia. Al confirmar, PedidoService lee esta fila, crea pedido +
 * detalle_pedido + pedido_entrega en una sola transaccion y la borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_temporal', function (Blueprint $table) {
            $table->id('ptm_id');
            $table->unsignedBigInteger('ptm_usuid');

            $table->smallInteger('ptm_paso')->default(1);
            $table->jsonb('ptm_entrega')->nullable();
            $table->jsonb('ptm_factura')->nullable();

            $table->timestamp('ptm_creado')->nullable();
            $table->timestamp('ptm_actual')->nullable();

            $table->foreign('ptm_usuid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('ptm_usuid', 'uq_ptm_usuid');
        });

        DB::statement('ALTER TABLE pedido_temporal
            ADD CONSTRAINT ck_ptm_paso CHECK (ptm_paso BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_temporal');
    }
};