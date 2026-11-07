<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La validación del comprobante vive en el comprobante, igual que
 * pago_comprobante (2026_10_30_000002). Validar NO es cobrar: el cobro se
 * registra después, contra la factura (cobro_venta).
 *
 * Las columnas spa_* de verificación dejan de escribirse pero no se borran:
 * los servicios del ciclo anterior conservan ahí su historia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicio_comprobante', function (Blueprint $table) {
            $table->string('sco_observ', 255)->nullable()->after('sco_verifi');
            $table->unsignedInteger('sco_usuver')->nullable()->after('sco_observ');
            $table->timestamp('sco_fechver')->nullable()->after('sco_usuver');

            $table->foreign('sco_usuver', 'fk_sco_usuver')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement('
            UPDATE servicio_comprobante sc
               SET sco_usuver  = sp.spa_usuver,
                   sco_fechver = sp.spa_fechver
              FROM servicio_pago sp
             WHERE sp.spa_id = sc.sco_spaid
               AND sc.sco_verifi = true
        ');

        DB::statement('
            UPDATE servicio_comprobante
               SET sco_fechver = sco_subido
             WHERE sco_verifi = true AND sco_fechver IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('servicio_comprobante', function (Blueprint $table) {
            $table->dropForeign('fk_sco_usuver');
            $table->dropColumn(['sco_observ', 'sco_usuver', 'sco_fechver']);
        });
    }
};