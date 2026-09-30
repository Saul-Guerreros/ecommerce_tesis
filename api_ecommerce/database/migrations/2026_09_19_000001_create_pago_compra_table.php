<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_compra', function (Blueprint $table) {
            $table->increments('pgo_id');
            $table->unsignedInteger('pgo_cuoid');

            $table->date('pgo_fecha');
            $table->decimal('pgo_importe', 14, 2);
            $table->string('pgo_formapa', 20); // Efectivo, Transferencia, Cheque, Otro
            $table->string('pgo_observ', 150)->nullable();
            $table->unsignedInteger('pgo_usuid')->nullable();
            $table->timestamp('pgo_creado')->useCurrent();

            // restrict, no cascade: un pago es un registro contable, nunca
            // se borra en cascada aunque se toque la cuota o la factura.
            $table->foreign('pgo_cuoid', 'fk_pgo_cuo')
                  ->references('cuo_id')->on('cuota_compra')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_importe CHECK (pgo_importe > 0)");
        DB::statement("ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_formapa CHECK (pgo_formapa IN ('Efectivo','Transferencia','Cheque','Otro'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_compra');
    }
};