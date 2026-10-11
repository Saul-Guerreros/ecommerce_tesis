<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de motivos de rechazo de un pago.
 *
 * Se modela como tabla y no como CHECK de texto libre por la misma razon
 * que motivo_nota_credito: el administrador elige de una lista cerrada,
 * los motivos pueden crecer sin tocar codigo y los reportes pueden
 * agrupar por motivo ("cuantos pagos se rechazaron por comprobante
 * ilegible este mes").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivo_rechazo_pago', function (Blueprint $table) {
            $table->smallIncrements('mrp_id');
            $table->string('mrp_codigo', 30);
            $table->string('mrp_descri', 100);

            // Si el motivo exige que el administrador escriba un detalle
            // ademas de elegir la opcion (p. ej. "Otro").
            $table->boolean('mrp_requdet')->default(true);

            $table->smallInteger('mrp_estado')->default(1);
            $table->timestamp('mrp_creado')->useCurrent();

            $table->unique('mrp_codigo', 'uq_mrp_codigo');
        });

        DB::statement('ALTER TABLE motivo_rechazo_pago
            ADD CONSTRAINT ck_mrp_estado CHECK (mrp_estado IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('motivo_rechazo_pago');
    }
};