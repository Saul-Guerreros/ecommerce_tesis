<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante del pago de una cuota. Espejo de servicio_comprobante.
 *
 * uq_cco_pago: al reemplazar un comprobante rechazado se ACTUALIZA la
 * fila, no se agrega otra. Un pago tiene un archivo vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_comprobante', function (Blueprint $table) {
            $table->id('ccm_id');
            $table->unsignedBigInteger('ccm_cpaid');

            $table->string('ccm_archiv', 255);
            $table->boolean('ccm_verifi')->default(false);
            $table->timestamp('ccm_subido')->useCurrent();

            $table->foreign('ccm_cpaid', 'fk_ccm_cpa')
                  ->references('cpa_id')->on('cuota_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('ccm_cpaid', 'uq_ccm_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_comprobante');
    }
};