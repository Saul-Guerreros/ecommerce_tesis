<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pedido', function (Blueprint $table) {
            $table->id('dpd_id');
            $table->unsignedBigInteger('dpd_pedid');
            $table->unsignedInteger('dpd_prdid');
            $table->string('dpd_descri', 150);
            $table->decimal('dpd_cantid', 14, 2);
            $table->decimal('dpd_precio', 14, 2);
            $table->decimal('dpd_subtot', 14, 2);
            $table->decimal('dpd_total', 14, 2);
            $table->decimal('dpd_iva', 14, 2)->default(0);
            $table->string('dpd_modpag', 10)->default('contado');
            $table->smallInteger('dpd_numcuo')->nullable();
            $table->decimal('dpd_recargopct', 5, 2)->nullable();
            $table->decimal('dpd_montocuo', 14, 2)->nullable();

            $table->foreign('dpd_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('dpd_prdid')->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE detalle_pedido ADD CONSTRAINT ck_dpd_modpag CHECK (dpd_modpag IN ('contado','credito'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedido');
    }
};