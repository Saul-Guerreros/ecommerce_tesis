<?php

use Database\Seeders\MotivoNotaCreditoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de cada motivo como DATO del catálogo, no como if por código:
 *
 *   mnc_canfij : la cantidad no se tipea, es el saldo de unidades vigentes
 *                (error de precio: afecta a todas las unidades).
 *   mnc_obsreq : la observación es obligatoria (hay que documentar el caso).
 *   mnc_destex : el destino de la mercadería no tiene sugerencia: el usuario
 *                debe elegirlo (defectuosos NO vuelven solos al stock vendible).
 *
 * Mismo criterio que mnc_afesto: el catálogo decide, nadie pregunta por el código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motivo_nota_credito', function (Blueprint $table) {
            $table->smallInteger('mnc_canfij')->default(0)->after('mnc_afesto');
            $table->smallInteger('mnc_obsreq')->default(0)->after('mnc_canfij');
            $table->smallInteger('mnc_destex')->default(0)->after('mnc_obsreq');
        });

        foreach (['mnc_canfij', 'mnc_obsreq', 'mnc_destex'] as $col) {
            DB::statement("ALTER TABLE motivo_nota_credito ADD CONSTRAINT ck_{$col} CHECK ({$col} IN (0, 1))");
        }

        (new MotivoNotaCreditoSeeder)->run();   // idempotente
    }

    public function down(): void
    {
        foreach (['mnc_canfij', 'mnc_obsreq', 'mnc_destex'] as $col) {
            DB::statement("ALTER TABLE motivo_nota_credito DROP CONSTRAINT IF EXISTS ck_{$col}");
        }

        Schema::table('motivo_nota_credito', function (Blueprint $table) {
            $table->dropColumn(['mnc_canfij', 'mnc_obsreq', 'mnc_destex']);
        });
    }
};