<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite que venta_cab exista ANTES de emitirse (estado 0 = pendiente de
 * emision). Los datos fiscales pasan a ser NULLABLE, pero un CHECK exige
 * que esten completos en cuanto la factura sale del estado pendiente.
 */
return new class extends Migration
{
    private const FISCALES = [
        'ven_timid', 'ven_cajid', 'ven_usuid',
        'ven_timbrado', 'ven_timb_inicio', 'ven_timb_venc',
        'ven_emp_ruc', 'ven_emp_razon',
        'ven_establec', 'ven_puntoexp', 'ven_numero', 'ven_fchaem',
    ];

    public function up(): void
    {
        foreach (self::FISCALES as $columna) {
            DB::statement("ALTER TABLE venta_cab ALTER COLUMN {$columna} DROP NOT NULL");
        }

        // Sin default: un borrador NO tiene fecha de emision.
        DB::statement("ALTER TABLE venta_cab ALTER COLUMN ven_fchaem DROP DEFAULT");

        DB::statement("ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_estado");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_estado
            CHECK (ven_estado IN (0, 1, 2, 9))");   // 0=Pendiente de emision

        // Pendiente (0) y anulada-sin-emitir (9) pueden no tener datos fiscales.
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_fiscal_completo CHECK (
            ven_estado IN (0, 9) OR (
                ven_timid IS NOT NULL AND ven_cajid IS NOT NULL AND ven_usuid IS NOT NULL
                AND ven_timbrado IS NOT NULL AND ven_timb_inicio IS NOT NULL
                AND ven_timb_venc IS NOT NULL AND ven_emp_ruc IS NOT NULL
                AND ven_emp_razon IS NOT NULL AND ven_establec IS NOT NULL
                AND ven_puntoexp IS NOT NULL AND ven_numero IS NOT NULL
                AND ven_fchaem IS NOT NULL
            ))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_fiscal_completo");

        // Los borradores no se pueden conservar con las columnas NOT NULL.
        DB::statement("DELETE FROM venta_detalle WHERE vnd_venid IN
            (SELECT ven_id FROM venta_cab WHERE ven_numero IS NULL)");
        DB::statement("DELETE FROM venta_cab WHERE ven_numero IS NULL");

        DB::statement("ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_estado");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_estado CHECK (ven_estado IN (1, 2, 9))");
        DB::statement("ALTER TABLE venta_cab ALTER COLUMN ven_fchaem SET DEFAULT CURRENT_TIMESTAMP");

        foreach (self::FISCALES as $columna) {
            DB::statement("ALTER TABLE venta_cab ALTER COLUMN {$columna} SET NOT NULL");
        }
    }
};