<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cobro_venta cobra también facturas de servicio (anticipo y final).
 *
 *   cob_spaid          pago del servicio cobrado (junto a cob_ppaid de pedido).
 *   uq_cob_spa_activo  un pago del servicio, como máximo UN cobro activo.
 *   ck_cob_origen      un cobro no viene de un pedido y de un servicio a la vez.
 *
 * Datos históricos: las facturas de servicio del ciclo anterior no tienen
 * cobro_venta (el "cobro" era el servicio_pago CONFIRMADO). Se crean y, si
 * cubren el total, la factura pasa a Cobrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobro_venta', function (Blueprint $table) {
            $table->unsignedBigInteger('cob_spaid')->nullable()->after('cob_ppaid');

            $table->foreign('cob_spaid', 'fk_cob_spa')
                  ->references('spa_id')->on('servicio_pago')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_cob_spa_activo
            ON cobro_venta (cob_spaid) WHERE cob_spaid IS NOT NULL AND cob_estado = 1');

        DB::statement('ALTER TABLE cobro_venta ADD CONSTRAINT ck_cob_origen
            CHECK (cob_ppaid IS NULL OR cob_spaid IS NULL)');

        DB::statement("
            INSERT INTO cobro_venta (
                cob_venid, cob_mpaid, cob_monto, cob_nrotrx, cob_banco, cob_fecha,
                cob_observ, cob_spaid, cob_usuid, cob_estado, cob_creado
            )
            SELECT ven.ven_id,
                   spa.spa_mpaid,
                   COALESCE(spa.spa_montver, spa.spa_monto),
                   spa.spa_nrotrx,
                   spa.spa_banco,
                   COALESCE(spa.spa_fechpag, spa.spa_fechver::date, ven.ven_fchaem),
                   spa.spa_observ,
                   spa.spa_id,
                   COALESCE(spa.spa_usucob, spa.spa_usuver, ven.ven_usuid),
                   1,
                   COALESCE(spa.spa_fechver, spa.spa_creado)
              FROM servicio_pago spa
              JOIN estado_pago epa ON epa.epa_id = spa.spa_epaid
              JOIN venta_cab ven   ON ven.ven_sseid = spa.spa_sseid
                                  AND ven.ven_estado <> 9
                                  AND ven.ven_tipo = 'VENTA'
             WHERE epa.epa_codigo = 'CONFIRMADO'
               AND NOT EXISTS (SELECT 1 FROM cobro_venta c
                                WHERE c.cob_spaid = spa.spa_id AND c.cob_estado = 1)
        ");

        DB::statement('
            UPDATE venta_cab v
               SET ven_estado = 2
             WHERE v.ven_sseid IS NOT NULL
               AND v.ven_estado = 1
               AND (SELECT COALESCE(SUM(c.cob_monto), 0)
                      FROM cobro_venta c
                     WHERE c.cob_venid = v.ven_id AND c.cob_estado = 1) + 0.01 >= v.ven_total
        ');
    }

    public function down(): void
    {
        DB::statement('UPDATE venta_cab SET ven_estado = 1 WHERE ven_sseid IS NOT NULL AND ven_estado = 2');
        DB::statement('DELETE FROM cobro_venta WHERE cob_spaid IS NOT NULL');

        DB::statement('ALTER TABLE cobro_venta DROP CONSTRAINT IF EXISTS ck_cob_origen');
        DB::statement('DROP INDEX IF EXISTS uq_cob_spa_activo');

        Schema::table('cobro_venta', function (Blueprint $table) {
            $table->dropForeign('fk_cob_spa');
            $table->dropColumn('cob_spaid');
        });
    }
};