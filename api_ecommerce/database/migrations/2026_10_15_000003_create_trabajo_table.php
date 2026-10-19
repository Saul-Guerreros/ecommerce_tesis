<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Catálogo de trabajos (mano de obra) con precio de lista.
 * Alimenta las líneas MANO_OBRA de presupuesto_detalle (psd_trbid).
 *
 * El precio de acá es una SUGERENCIA: al presupuestar se copia a
 * psd_precio, así un cambio de tarifa no altera presupuestos ya emitidos
 * (mismo criterio que dpd_precio en detalle_pedido).
 *
 * trb_tivid sigue el prefijo real de tipo_iva (tiv_), igual que prd_tivid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajo', function (Blueprint $table) {
            $table->increments('trb_id');
            $table->string('trb_codigo', 20);
            $table->string('trb_descri', 100);
            $table->decimal('trb_precio', 14, 2)->default(0);
            $table->unsignedInteger('trb_tivid');
            $table->smallInteger('trb_estado')->default(1);
            $table->timestamp('trb_creado')->useCurrent();

            $table->unique('trb_codigo', 'uq_trb_codigo');

            $table->foreign('trb_tivid', 'fk_trb_tiv')
                  ->references('tiv_id')->on('tipo_iva')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('trb_tivid', 'ix_trb_tivid');
        });

        DB::statement("ALTER TABLE trabajo ADD CONSTRAINT ck_trb_estado CHECK (trb_estado IN (0, 1))");
        DB::statement("ALTER TABLE trabajo ADD CONSTRAINT ck_trb_precio CHECK (trb_precio >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajo');
    }
};
