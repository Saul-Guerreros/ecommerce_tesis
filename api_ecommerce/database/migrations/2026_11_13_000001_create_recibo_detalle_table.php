<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle del recibo: que cuotas cubre cada cobro.
 *
 * Existe porque un recibo puede saldar VARIAS cuotas de una vez (el
 * cliente paga las cuotas 3 y 4 con una sola transferencia). Con el
 * vinculo en la cabecera eso era imposible.
 *
 * Un recibo de venta al CONTADO no tiene detalle: salda la factura
 * entera, no cuotas. Por eso la relacion es 0..N y no 1..N.
 *
 * rdt_monto siempre coincide hoy con cco_monto, porque cta_cobrar no
 * admite parciales. Se guarda igual para no rediseñar el esquema si el
 * dia de mañana se permiten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibo_detalle', function (Blueprint $table) {
            $table->id('rdt_id');
            $table->unsignedInteger('rdt_recid');     // recibo.rec_id es serial (integer)
            $table->unsignedBigInteger('rdt_ccoid');  // cta_cobrar.cco_id es bigserial

            $table->decimal('rdt_monto', 14, 2);

            $table->foreign('rdt_recid', 'fk_rdt_recibo')
                  ->references('rec_id')->on('recibo')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('rdt_ccoid', 'fk_rdt_cuota')
                  ->references('cco_id')->on('cta_cobrar')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Una cuota no se repite dentro del mismo recibo.
            $table->unique(['rdt_recid', 'rdt_ccoid'], 'uq_rdt_recibo_cuota');

            $table->index('rdt_ccoid', 'ix_rdt_cuota');
        });

        DB::statement('ALTER TABLE recibo_detalle ADD CONSTRAINT ck_rdt_monto CHECK (rdt_monto > 0)');

        // El candado que antes vivia en la cabecera (uq_cob_cuota_activo):
        // una cuota no puede estar en dos recibos activos a la vez. Mira
        // el estado del recibo, porque anularlo libera la cuota.
               // Un indice parcial no admite subconsultas, asi que el candado va
        // por trigger: una cuota no puede estar en dos recibos ACTIVOS.
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_rdt_cuota_unica() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                      FROM recibo_detalle d
                      JOIN recibo r ON r.rec_id = d.rdt_recid
                     WHERE d.rdt_ccoid = NEW.rdt_ccoid
                       AND d.rdt_recid <> NEW.rdt_recid
                       AND r.rec_estado = 1
                ) THEN
                    RAISE EXCEPTION 'La cuota % ya figura en un recibo activo', NEW.rdt_ccoid;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER tg_rdt_cuota_unica
            BEFORE INSERT OR UPDATE ON recibo_detalle
            FOR EACH ROW EXECUTE FUNCTION fn_rdt_cuota_unica();
        ");
        // Numeracion propia del recibo. No lleva timbrado: en Paraguay el
        // recibo no es un documento fiscal, asi que no consume talonario.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS recibo_numero_seq START 1');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS tg_rdt_cuota_unica ON recibo_detalle');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_rdt_cuota_unica()');

        DB::statement('DROP SEQUENCE IF EXISTS recibo_numero_seq');

        Schema::dropIfExists('recibo_detalle');
    }
};