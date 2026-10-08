<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_historial_estado', function (Blueprint $table) {
            $table->id('peh_id');
            $table->unsignedBigInteger('peh_pedid');
            $table->unsignedInteger('peh_usuid')->nullable();
            $table->string('peh_tipo', 10)->default('PEDIDO');
            $table->unsignedSmallInteger('peh_estant')->nullable();
            $table->unsignedSmallInteger('peh_estid');
            $table->string('peh_coment', 500)->nullable();
            $table->string('peh_ip', 45)->nullable();
            $table->timestamp('peh_fecha')->useCurrent();

            $table->foreign('peh_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('peh_usuid')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement("ALTER TABLE pedido_historial_estado ADD CONSTRAINT ck_peh_tipo CHECK (peh_tipo IN ('PAGO','PEDIDO','ENVIO','NOTA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_historial_estado');
    }
};