<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega inmediata y correo posterior al cobro (guía 0.54.1, 6.6.A y 10.3).
 *
 * Estado nuevo 4 = PREPARADO: la API devolvió CDC, XML y PDF provisionales
 * (HTTP 202) y sigue la aprobación en segundo plano. Permite registrar el
 * cobro, pero NO es aprobación: una final solo aplica un anticipo en 2.
 *
 *   vfe_entinm           emitida con entrega_inmediata (habilita POST /correo).
 *   vfe_correo_pend      tarea durable: hay que pedir POST /facturas/{cdc}/correo.
 *                        Se marca al registrar el cobro y se apaga cuando la
 *                        API acepta el envío o el seguimiento.
 *   vfe_correo_prog      la API aceptó encargarse del seguimiento (programado).
 *   vfe_correo_intentos  reintentos de la solicitud de correo.
 *
 * vfe_correo_enviado / _detalle / _fecha (2026_10_24) siguen guardando el
 * último resultado informado por la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_fe', function (Blueprint $table) {
            $table->boolean('vfe_entinm')->default(false)->after('vfe_estado');
            $table->boolean('vfe_correo_pend')->default(false)->after('vfe_correo_fecha');
            $table->boolean('vfe_correo_prog')->nullable()->after('vfe_correo_pend');
            $table->smallInteger('vfe_correo_intentos')->default(0)->after('vfe_correo_prog');
        });

        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_estado');
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_estado
            CHECK (vfe_estado IN (1, 2, 3, 4))');

        // PREPARADO sin CDC o sin archivos no sirve para registrar un cobro.
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_preparado
            CHECK (vfe_estado <> 4 OR (vfe_cdc IS NOT NULL
                   AND vfe_xml_ruta IS NOT NULL AND vfe_pdf_ruta IS NOT NULL))');

        // El correo posterior solo existe para documentos de entrega inmediata.
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_correo_pend
            CHECK (vfe_correo_pend = false OR vfe_entinm = true)');

        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_correo_intentos
            CHECK (vfe_correo_intentos >= 0)');

        DB::statement('CREATE INDEX ix_vfe_correo_pend ON venta_fe (vfe_correo_pend)
            WHERE vfe_correo_pend = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ix_vfe_correo_pend');
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_correo_intentos');
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_correo_pend');
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_preparado');

        DB::statement('UPDATE venta_fe SET vfe_estado = 1 WHERE vfe_estado = 4');
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_estado');
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_estado
            CHECK (vfe_estado IN (1, 2, 3))');

        Schema::table('venta_fe', function (Blueprint $table) {
            $table->dropColumn(['vfe_entinm', 'vfe_correo_pend', 'vfe_correo_prog', 'vfe_correo_intentos']);
        });
    }
};