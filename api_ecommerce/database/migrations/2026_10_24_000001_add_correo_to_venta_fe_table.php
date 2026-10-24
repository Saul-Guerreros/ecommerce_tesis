<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado del correo que la API intenta mandarle al cliente cuando
 * aprueba la factura (guia, capitulo 10).
 *
 * Un correo que no salio NO cambia el comprobante: sigue aprobado, con su
 * CDC. Por eso esto vive aparte de vfe_estado y solo informa, para que el
 * administrador sepa si hay que reenviar la factura.
 *
 *   vfe_correo_enviado:  NULL  = no se intento (no se pidio, el cliente
 *                                no eligio CORREO o no dejo correo), o no
 *                                se supo (la factura se recupero tras un corte)
 *                        TRUE  = salio
 *                        FALSE = se intento y no salio
 *   vfe_correo_detalle:  el destinatario si salio; el motivo si no salio
 *   vfe_correo_fecha:    cuando se registro el resultado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_fe', function (Blueprint $table) {
            $table->boolean('vfe_correo_enviado')->nullable()->after('vfe_confirmado');
            $table->text('vfe_correo_detalle')->nullable()->after('vfe_correo_enviado');
            $table->timestamp('vfe_correo_fecha')->nullable()->after('vfe_correo_detalle');
        });
    }

    public function down(): void
    {
        Schema::table('venta_fe', function (Blueprint $table) {
            $table->dropColumn(['vfe_correo_enviado', 'vfe_correo_detalle', 'vfe_correo_fecha']);
        });
    }
};