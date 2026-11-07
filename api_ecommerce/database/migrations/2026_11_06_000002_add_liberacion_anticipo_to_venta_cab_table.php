<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liberación del anticipo aplicado en una factura final (guía 0.54.1, cap. 30).
 *
 * Cuando la final deja de existir como venta (NC por el total, o anulación
 * manual de una final de neto cero), lo aplicado vuelve a quedar disponible
 * en la factura de anticipo para acreditarlo con NC y devolverlo.
 *
 *   ven_antlib   importe liberado (0 = sigue aplicado; hoy siempre 0 o ven_antapl).
 *   ven_fechlib  cuándo; ven_usulib quién.
 *   ven_ncvlib   NC de la final que la provocó (NULL = liberación manual).
 *
 * disponible del anticipo = total − NC − SUM(ven_antapl − ven_antlib).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cab', function (Blueprint $table) {
            $table->decimal('ven_antlib', 14, 2)->default(0)->after('ven_antapl');
            $table->timestamp('ven_fechlib')->nullable()->after('ven_antlib');
            $table->unsignedInteger('ven_usulib')->nullable()->after('ven_fechlib');
            $table->unsignedInteger('ven_ncvlib')->nullable()->after('ven_usulib');

            $table->foreign('ven_usulib', 'fk_ven_usulib')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('ven_ncvlib', 'fk_ven_ncvlib')->references('ncv_id')->on('nota_credito_venta')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_antlib
            CHECK (ven_antlib >= 0 AND ven_antlib <= ven_antapl)');

        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_antlib_fecha
            CHECK ((ven_antlib = 0) = (ven_fechlib IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_antlib_fecha');
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_antlib');

        Schema::table('venta_cab', function (Blueprint $table) {
            $table->dropForeign('fk_ven_ncvlib');
            $table->dropForeign('fk_ven_usulib');
            $table->dropColumn(['ven_antlib', 'ven_fechlib', 'ven_usulib', 'ven_ncvlib']);
        });
    }
};