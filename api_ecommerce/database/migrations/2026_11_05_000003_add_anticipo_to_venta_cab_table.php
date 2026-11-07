<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas de anticipo y su aplicación en la factura final (guía 0.54.1, cap. 29).
 *
 *   ven_tipo      VENTA (habitual) | ANTICIPO (tipo_transaccion 9).
 *   ven_antvenid  en la FINAL: la factura de anticipo que aplica (su CDC
 *                 va en anticipo.cdc). La API admite UN anticipo por final.
 *   ven_antapl    en la FINAL: importe total aplicado (con IVA). Los totales
 *                 de la cabecera (ven_total, gravadas, IVA) guardan el NETO,
 *                 igual que F012 del XML: así saldo() no cambia.
 *
 * La API no lleva el saldo del anticipo (cap. 29): este sistema lo calcula
 *   disponible = total del anticipo - notas de crédito - aplicado en finales.
 *
 * Índices:
 *   uq_venta_servicio           una factura VENTA vigente por solicitud.
 *   uq_venta_servicio_anticipo  una factura ANTICIPO vigente por solicitud.
 *   uq_venta_anticipo_aplicado  un anticipo se aplica en UNA final vigente:
 *                               impide consumir dos veces el mismo saldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cab', function (Blueprint $table) {
            $table->string('ven_tipo', 8)->default('VENTA')->after('ven_sseid');
            $table->unsignedInteger('ven_antvenid')->nullable()->after('ven_tipo');
            $table->decimal('ven_antapl', 14, 2)->default(0)->after('ven_antvenid');

            $table->foreign('ven_antvenid', 'fk_ven_anticipo')
                  ->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_tipo
            CHECK (ven_tipo IN ('VENTA', 'ANTICIPO'))");

        // El anticipo es de servicio técnico, al contado y no aplica otro anticipo.
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_tipo_anticipo
            CHECK (ven_tipo <> 'ANTICIPO'
                   OR (ven_sseid IS NOT NULL AND ven_condicion = 1 AND ven_antvenid IS NULL))");

        // Hay importe aplicado si y solo si hay anticipo referenciado.
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_antapl
            CHECK ((ven_antvenid IS NULL AND ven_antapl = 0)
                OR (ven_antvenid IS NOT NULL AND ven_antapl > 0))");

        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_antvenid_propio
            CHECK (ven_antvenid IS NULL OR ven_antvenid <> ven_id)");

        DB::statement('DROP INDEX IF EXISTS uq_venta_servicio');

        DB::statement("CREATE UNIQUE INDEX uq_venta_servicio
            ON venta_cab (ven_sseid)
            WHERE ven_sseid IS NOT NULL AND ven_estado <> 9 AND ven_tipo = 'VENTA'");

        DB::statement("CREATE UNIQUE INDEX uq_venta_servicio_anticipo
            ON venta_cab (ven_sseid)
            WHERE ven_sseid IS NOT NULL AND ven_estado <> 9 AND ven_tipo = 'ANTICIPO'");

        DB::statement('CREATE UNIQUE INDEX uq_venta_anticipo_aplicado
            ON venta_cab (ven_antvenid)
            WHERE ven_antvenid IS NOT NULL AND ven_estado <> 9');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_venta_anticipo_aplicado');
        DB::statement('DROP INDEX IF EXISTS uq_venta_servicio_anticipo');
        DB::statement('DROP INDEX IF EXISTS uq_venta_servicio');

        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_antvenid_propio');
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_antapl');
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_tipo_anticipo');
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_tipo');

        Schema::table('venta_cab', function (Blueprint $table) {
            $table->dropForeign('fk_ven_anticipo');
            $table->dropColumn(['ven_tipo', 'ven_antvenid', 'ven_antapl']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_venta_servicio
            ON venta_cab (ven_sseid) WHERE ven_sseid IS NOT NULL AND ven_estado <> 9');
    }
};