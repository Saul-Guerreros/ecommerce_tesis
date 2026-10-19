<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Repuestos REALMENTE usados en la OT: el HECHO (regla 4).
 * presupuesto_detalle es la intención; esto es lo que se descuenta del
 * stock al cerrar (cada línea genera un movimiento_stock 'S' con mst_osrid).
 *
 * osr_presup: true = venía del presupuesto original (v1);
 *             false = entró por un presupuesto complementario (ajuste).
 * osr_cantap: cantidad APROBADA por el cliente (suma de las versiones). Es el
 *             tope de osr_cantid: usar más exige otro complementario
 *             (el cliente nunca paga algo que no aprobó). Columna agregada al brief.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_servicio_repuesto', function (Blueprint $table) {
            $table->id('osr_id');
            $table->unsignedBigInteger('osr_oseid');
            $table->unsignedInteger('osr_prdid');
            $table->unsignedInteger('osr_depid');
            $table->decimal('osr_cantap', 14, 4);
            $table->decimal('osr_cantid', 14, 4);
            $table->decimal('osr_precio', 14, 2);
            $table->boolean('osr_presup')->default(true);

            $table->unique(['osr_oseid', 'osr_prdid'], 'uq_osr_ose_prd');

            $table->foreign('osr_oseid', 'fk_osr_ose')->references('ose_id')->on('orden_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('osr_prdid', 'fk_osr_prd')->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('osr_depid', 'fk_osr_dep')->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('osr_prdid', 'ix_osr_prdid');
        });

        DB::statement("ALTER TABLE orden_servicio_repuesto ADD CONSTRAINT ck_osr_cantid
            CHECK (osr_cantid >= 0 AND osr_cantid <= osr_cantap AND osr_precio >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_repuesto');
    }
};
