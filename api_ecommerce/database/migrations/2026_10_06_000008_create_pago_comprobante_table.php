<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_comprobante', function (Blueprint $table) {
            $table->id('pco_id');
            $table->unsignedBigInteger('pco_pedid');
            $table->string('pco_archiv', 255);
            $table->boolean('pco_verifi')->default(false);
            $table->timestamp('pco_subido')->useCurrent();

            $table->foreign('pco_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_comprobante');
    }
};