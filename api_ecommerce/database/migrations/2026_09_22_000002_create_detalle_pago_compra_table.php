<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pago_compra', function (Blueprint $table) {
            $table->increments('dpc_id');
            $table->unsignedInteger('dpc_pgoid');
            $table->unsignedInteger('dpc_cuoid');
            $table->decimal('dpc_importe', 14, 2);

            // Si se borra el pago (no deberia pasar en produccion), se
            // borra su detalle en cascada. La cuota NUNCA se borra si
            // tiene pagos aplicados (restrict).
            $table->foreign('dpc_pgoid', 'fk_dpc_pgo')
                  ->references('pgo_id')->on('pago_compra')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('dpc_cuoid', 'fk_dpc_cuo')
                  ->references('cuo_id')->on('cuota_compra')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Un mismo pago no puede aplicar dos veces a la misma cuota.
            $table->unique(['dpc_pgoid', 'dpc_cuoid'], 'uq_dpc_pgo_cuo');
        });

        DB::statement('ALTER TABLE detalle_pago_compra ADD CONSTRAINT ck_dpc_importe CHECK (dpc_importe > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pago_compra');
    }
};