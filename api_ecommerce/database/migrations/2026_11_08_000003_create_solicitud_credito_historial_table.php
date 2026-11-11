<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de cambios de estado de la solicitud.
 *
 * sch_escant es nullable porque el primer registro (la solicitud nace)
 * no tiene estado anterior.
 *
 * sch_usuid es nullable y ON DELETE SET NULL conceptual (acá RESTRICT
 * para no perder trazabilidad): el historial debe sobrevivir aunque el
 * usuario se desactive. Un registro de auditoria que se borra con su
 * autor no sirve como auditoria.
 *
 * El bloque de backfill crea la fila inicial de las solicitudes que ya
 * existen en la base, para que el panel no muestre un historial vacio
 * en las solicitudes generadas antes de esta migracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_credito_historial', function (Blueprint $table) {
            $table->id('sch_id');
            $table->unsignedBigInteger('sch_scrid');

            $table->unsignedSmallInteger('sch_escant')->nullable();
            $table->unsignedSmallInteger('sch_escnue');
            $table->unsignedBigInteger('sch_usuid')->nullable();

            $table->string('sch_observ', 300)->nullable();
            $table->timestamp('sch_fecha')->useCurrent();

            $table->foreign('sch_scrid', 'fk_sch_scr')
                  ->references('scr_id')->on('solicitud_credito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('sch_escant', 'fk_sch_escant')
                  ->references('esc_id')->on('estado_solicitud')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('sch_escnue', 'fk_sch_escnue')
                  ->references('esc_id')->on('estado_solicitud')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('sch_usuid', 'fk_sch_usu')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index(['sch_scrid', 'sch_fecha'], 'ix_sch_scr_fecha');
        });

        // ---------- Backfill de solicitudes ya existentes ----------
        $existentes = DB::table('solicitud_credito')
            ->select('scr_id', 'scr_escid', 'scr_usuid', 'scr_creado')
            ->get();

        foreach ($existentes as $solicitud) {
            DB::table('solicitud_credito_historial')->insert([
                'sch_scrid'  => $solicitud->scr_id,
                'sch_escant' => null,
                'sch_escnue' => $solicitud->scr_escid,
                'sch_usuid'  => $solicitud->scr_usuid,
                'sch_observ' => 'Solicitud generada por el cliente.',
                'sch_fecha'  => $solicitud->scr_creado,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_credito_historial');
    }
};