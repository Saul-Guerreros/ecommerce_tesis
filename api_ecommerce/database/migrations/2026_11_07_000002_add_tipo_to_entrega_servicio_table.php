<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La salida física del equipo tiene dos orígenes:
 *   ENTREGA     servicio terminado (LISTA -> ENTREGADA), con su cobro.
 *   DEVOLUCION  solicitud cancelada con el equipo en el taller, sin cobro.
 * Misma trazabilidad (quién, cuándo, a quién, accesorios) y la misma
 * constancia de retiro. uq_ese_sseid sigue valiendo: una salida por solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_servicio', function (Blueprint $table) {
            $table->string('ese_tipo', 12)->default('ENTREGA')->after('ese_sseid');
        });

        DB::statement("ALTER TABLE entrega_servicio ADD CONSTRAINT ck_ese_tipo
            CHECK (ese_tipo IN ('ENTREGA', 'DEVOLUCION'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE entrega_servicio DROP CONSTRAINT IF EXISTS ck_ese_tipo');

        Schema::table('entrega_servicio', function (Blueprint $table) {
            $table->dropColumn('ese_tipo');
        });
    }
};