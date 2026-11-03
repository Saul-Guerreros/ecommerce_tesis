<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revierte el borrador de factura (ven_estado = 0).
 *
 * El pedido deja de crear un registro de venta al confirmarse: venta_cab
 * vuelve a nacer SIEMPRE numerada y con su snapshot fiscal completo. Con
 * eso, el estado 0 y el CHECK que lo toleraba pierden sentido y las
 * columnas fiscales recuperan su NOT NULL, que es la garantia real de que
 * no puede guardarse una factura a medio emitir.
 *
 * No se edita la migracion anterior (2026_10_27_000001): una migracion ya
 * ejecutada en cualquier entorno no se toca, se compensa con una nueva.
 */
return new class extends Migration
{
    /** Las mismas 12 columnas que 2026_10_27_000001 hizo nullable. */
    private const FISCALES = [
        'ven_timid', 'ven_cajid', 'ven_usuid',
        'ven_timbrado', 'ven_timb_inicio', 'ven_timb_venc',
        'ven_emp_ruc', 'ven_emp_razon',
        'ven_establec', 'ven_puntoexp', 'ven_numero', 'ven_fchaem',
    ];

    public function up(): void
    {
        // 1. Fuera los borradores (pendientes y los anulados sin numerar).
        //    El criterio es ven_numero IS NULL y no ven_estado = 0, porque
        //    anularBorradorDePedido() los dejaba en 9 sin numero.
        DB::statement('DELETE FROM venta_fe WHERE vfe_venid IN
            (SELECT ven_id FROM venta_cab WHERE ven_numero IS NULL)');

        DB::statement('DELETE FROM venta_detalle WHERE vnd_venid IN
            (SELECT ven_id FROM venta_cab WHERE ven_numero IS NULL)');

        DB::statement('DELETE FROM venta_cab WHERE ven_numero IS NULL');

        // 2. El CHECK del borrador ya no describe nada: lo reemplaza el
        //    NOT NULL de cada columna.
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_fiscal_completo');

        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_estado');
        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_estado
            CHECK (ven_estado IN (1, 2, 9))');   // 1=Emitida, 2=Cobrada, 9=Anulada

        // 3. Snapshot fiscal obligatorio otra vez.
        foreach (self::FISCALES as $columna) {
            DB::statement("ALTER TABLE venta_cab ALTER COLUMN {$columna} SET NOT NULL");
        }

        DB::statement('ALTER TABLE venta_cab ALTER COLUMN ven_fchaem SET DEFAULT CURRENT_TIMESTAMP');

        // 4. Se reafirman los dos invariantes que la instruccion pide dejar
        //    en su forma original. Ya estaban asi, pero declararlo aca deja
        //    el estado final del esquema en un solo archivo legible.
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_anulado');
        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_anulado
            CHECK ((ven_estado = 9) = (ven_anulado IS NOT NULL))');

        DB::statement('DROP INDEX IF EXISTS uq_venta_pedido');
        DB::statement('CREATE UNIQUE INDEX uq_venta_pedido
            ON venta_cab (ven_pedid) WHERE ven_pedid IS NOT NULL AND ven_estado <> 9');
    }

    public function down(): void
    {
        foreach (self::FISCALES as $columna) {
            DB::statement("ALTER TABLE venta_cab ALTER COLUMN {$columna} DROP NOT NULL");
        }

        DB::statement('ALTER TABLE venta_cab ALTER COLUMN ven_fchaem DROP DEFAULT');

        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_estado');
        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_estado
            CHECK (ven_estado IN (0, 1, 2, 9))');

        DB::statement('ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_fiscal_completo CHECK (
            ven_estado IN (0, 9) OR (
                ven_timid IS NOT NULL AND ven_cajid IS NOT NULL AND ven_usuid IS NOT NULL
                AND ven_timbrado IS NOT NULL AND ven_timb_inicio IS NOT NULL
                AND ven_timb_venc IS NOT NULL AND ven_emp_ruc IS NOT NULL
                AND ven_emp_razon IS NOT NULL AND ven_establec IS NOT NULL
                AND ven_puntoexp IS NOT NULL AND ven_numero IS NOT NULL
                AND ven_fchaem IS NOT NULL
            ))');
    }
};