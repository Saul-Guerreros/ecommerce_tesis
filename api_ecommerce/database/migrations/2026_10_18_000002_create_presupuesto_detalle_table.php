<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Líneas del presupuesto: la INTENCIÓN (regla 4). Lo consumido de
 * verdad va en orden_servicio_repuesto (Paso 5).
 *
 * Precios con IVA incluido (igual que detalle_pedido):
 *   psd_subtot = psd_cantid * psd_precio ; psd_iva = IVA contenido ; psd_total = psd_subtot
 *
 * Columnas agregadas al brief:
 *   psd_depid:  depósito del taller donde se reserva el repuesto.
 *   psd_reserv: true si al aprobar se pudo reservar (sto_reserv). Si no había
 *               stock, la línea queda "pendiente de compra" y el cierre de la
 *               OT no libera una reserva que nunca existió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_detalle', function (Blueprint $table) {
            $table->id('psd_id');
            $table->unsignedBigInteger('psd_pseid');
            $table->string('psd_tipo', 10);
            $table->unsignedInteger('psd_prdid')->nullable();
            $table->unsignedInteger('psd_trbid')->nullable();
            $table->unsignedInteger('psd_depid')->nullable();
            $table->string('psd_descri', 150);
            $table->decimal('psd_cantid', 14, 4);
            $table->decimal('psd_precio', 14, 2);
            $table->decimal('psd_subtot', 14, 2);
            $table->decimal('psd_iva', 14, 2)->default(0);
            $table->decimal('psd_total', 14, 2);
            $table->boolean('psd_reserv')->default(false);

            $table->foreign('psd_pseid', 'fk_psd_pse')->references('pse_id')->on('presupuesto_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('psd_prdid', 'fk_psd_prd')->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('psd_trbid', 'fk_psd_trb')->references('trb_id')->on('trabajo')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('psd_depid', 'fk_psd_dep')->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('psd_pseid', 'ix_psd_pseid');
            $table->index('psd_prdid', 'ix_psd_prdid');
        });

        // Exactamente uno de prdid/trbid según el tipo (regla del brief).
        DB::statement("ALTER TABLE presupuesto_detalle ADD CONSTRAINT ck_psd_tipo CHECK (
            (psd_tipo = 'REPUESTO'  AND psd_prdid IS NOT NULL AND psd_trbid IS NULL) OR
            (psd_tipo = 'MANO_OBRA' AND psd_trbid IS NOT NULL AND psd_prdid IS NULL AND psd_depid IS NULL AND psd_reserv = false)
        )");
        DB::statement("ALTER TABLE presupuesto_detalle ADD CONSTRAINT ck_psd_montos
            CHECK (psd_cantid > 0 AND psd_precio >= 0 AND psd_subtot >= 0 AND psd_iva >= 0 AND psd_total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_detalle');
    }
};
