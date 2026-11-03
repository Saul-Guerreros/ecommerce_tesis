<?php

use Database\Seeders\MotivoNotaCreditoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara el catálogo de motivos para la NC electrónica.
 *
 * La DNIT no acepta texto libre en el motivo: exige un código del 1 al 8
 * (1 Devolución y ajuste de precios, 2 Devolución, 3 Descuento,
 * 4 Bonificación, 5 Crédito incobrable, 6 Recupero de costo,
 * 7 Recupero de gasto, 8 Ajuste de precio).
 *
 * Guardarlo ahora en el catálogo evita que, cuando se implemente la
 * declaración electrónica, haya que escribir un match() de códigos
 * internos a códigos fiscales dentro del servicio.
 *
 * Nullable: un motivo interno que no tenga equivalente fiscal no bloquea
 * la emisión interna de la NC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motivo_nota_credito', function (Blueprint $table) {
            $table->smallInteger('mnc_dnit')->nullable()->after('mnc_afesto');
        });

        DB::statement('ALTER TABLE motivo_nota_credito ADD CONSTRAINT ck_mnc_dnit
            CHECK (mnc_dnit IS NULL OR mnc_dnit BETWEEN 1 AND 8)');

        (new MotivoNotaCreditoSeeder)->run();   // idempotente: rellena los existentes
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE motivo_nota_credito DROP CONSTRAINT IF EXISTS ck_mnc_dnit');

        Schema::table('motivo_nota_credito', function (Blueprint $table) {
            $table->dropColumn('mnc_dnit');
        });
    }
};