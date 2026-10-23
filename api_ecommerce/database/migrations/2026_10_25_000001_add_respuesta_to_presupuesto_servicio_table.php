<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 Paso 2 - Aprobación asistida del presupuesto.
 *
 * Hasta ahora solo el cliente respondía desde C4 (tienda). Ahora un funcionario puede
 * registrar la decisión que el cliente le comunicó en el mostrador, por teléfono o WhatsApp.
 *
 * - pse_canres: medio por el que respondió el cliente (WEB = desde la tienda).
 * - pse_usures: funcionario que registró la respuesta (null si respondió el propio cliente).
 * - pse_obsres: observación del funcionario; es la evidencia de la respuesta (decisión A.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_servicio', function (Blueprint $table) {
            $table->string('pse_canres', 10)->nullable();
            $table->unsignedInteger('pse_usures')->nullable();
            $table->string('pse_obsres', 255)->nullable();

            $table->foreign('pse_usures', 'fk_pse_usures')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE presupuesto_servicio ADD CONSTRAINT ck_pse_canres
            CHECK (pse_canres IS NULL OR pse_canres IN ('WEB', 'MOSTRADOR', 'TELEFONO', 'WHATSAPP'))");

        // Respuesta registrada por un funcionario: quién y con qué evidencia.
        DB::statement("ALTER TABLE presupuesto_servicio ADD CONSTRAINT ck_pse_respuesta
            CHECK (pse_canres IS NULL OR pse_canres = 'WEB'
                   OR (pse_usures IS NOT NULL AND pse_obsres IS NOT NULL))");

        // Backfill: lo respondido hasta hoy fue desde la tienda (los VENCIDOS no tienen respuesta).
        DB::statement("
            UPDATE presupuesto_servicio p
               SET pse_canres = 'WEB'
             WHERE p.pse_respon IS NOT NULL
               AND p.pse_eprid IN (SELECT epr_id FROM estado_presupuesto WHERE epr_codigo IN ('APROBADO', 'RECHAZADO'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE presupuesto_servicio DROP CONSTRAINT IF EXISTS ck_pse_respuesta');
        DB::statement('ALTER TABLE presupuesto_servicio DROP CONSTRAINT IF EXISTS ck_pse_canres');

        Schema::table('presupuesto_servicio', function (Blueprint $table) {
            $table->dropForeign('fk_pse_usures');
            $table->dropColumn(['pse_canres', 'pse_usures', 'pse_obsres']);
        });
    }
};
