<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 Paso 2 - "Registrar contacto con el cliente".
 *
 * Nuevo she_tipo CONTACTO (sin estado, como NOTA) con medio y resultado estructurados,
 * para que el panel sepa cuándo se contactó por última vez a un cliente que tiene un
 * presupuesto ENVIADO sin responder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicio_historial_estado', function (Blueprint $table) {
            $table->string('she_medio', 10)->nullable();
            $table->string('she_result', 12)->nullable();
            $table->index(['she_sseid', 'she_tipo'], 'ix_she_sse_tipo');
        });

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_tipo');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_tipo
            CHECK (she_tipo IN ('SERVICIO', 'INGRESO', 'EGRESO', 'PAGO', 'PRESUPUESTO', 'NOTA', 'CONTACTO'))");

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_estid');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_estid
            CHECK (she_tipo IN ('NOTA', 'CONTACTO') OR she_estid IS NOT NULL)");

        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_medio
            CHECK (she_medio IS NULL OR she_medio IN ('TELEFONO', 'WHATSAPP', 'CORREO', 'PRESENCIAL'))");
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_result
            CHECK (she_result IS NULL OR she_result IN ('CONTACTADO', 'NO_CONTESTA', 'PIDE_TIEMPO'))");

        // Un contacto siempre tiene medio y resultado; si no contestó, se explica.
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_contacto
            CHECK (she_tipo <> 'CONTACTO' OR (
                she_medio IS NOT NULL AND she_result IS NOT NULL
                AND (she_result <> 'NO_CONTESTA' OR she_coment IS NOT NULL)
            ))");
    }

    public function down(): void
    {
        // Los contactos registrados no tienen cabida en el esquema anterior.
        DB::table('servicio_historial_estado')->where('she_tipo', 'CONTACTO')->delete();

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT IF EXISTS ck_she_contacto');
        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT IF EXISTS ck_she_result');
        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT IF EXISTS ck_she_medio');

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_estid');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_estid
            CHECK (she_tipo = 'NOTA' OR she_estid IS NOT NULL)");

        DB::statement('ALTER TABLE servicio_historial_estado DROP CONSTRAINT ck_she_tipo');
        DB::statement("ALTER TABLE servicio_historial_estado ADD CONSTRAINT ck_she_tipo
            CHECK (she_tipo IN ('SERVICIO', 'INGRESO', 'EGRESO', 'PAGO', 'PRESUPUESTO', 'NOTA'))");

        Schema::table('servicio_historial_estado', function (Blueprint $table) {
            $table->dropIndex('ix_she_sse_tipo');
            $table->dropColumn(['she_medio', 'she_result']);
        });
    }
};
