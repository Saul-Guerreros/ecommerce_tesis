<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante de un pago del servicio. Espejo de pago_comprobante, pero
 * nace ya "uno por pago": no arrastra el pco_pedid heredado, porque la
 * solicitud se obtiene por servicio_pago.spa_sseid.
 *
 * uq_sco_pago: al reemplazar un comprobante rechazado se actualiza la
 * misma fila (igual que PagoPedidoService::reemplazarComprobante), así el
 * verificador nunca ve dos archivos para el mismo pago.
 *
 * El efectivo no genera comprobante (metodo_pago.mpa_requcom = false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_comprobante', function (Blueprint $table) {
            $table->id('sco_id');
            $table->unsignedBigInteger('sco_spaid');
            $table->string('sco_archiv', 255);
            $table->boolean('sco_verifi')->default(false);
            $table->timestamp('sco_subido')->useCurrent();

            $table->foreign('sco_spaid', 'fk_sco_spa')->references('spa_id')->on('servicio_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('sco_spaid', 'uq_sco_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_comprobante');
    }
};
