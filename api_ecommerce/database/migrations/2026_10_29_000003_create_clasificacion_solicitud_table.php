<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 7 - Clasificación automática de solicitudes.
 *  - *_ia  : lo que respondió la IA (INMUTABLE, base de las métricas).
 *  - vigentes (cls_tdiid, cls_cafid, cls_urgid): lo que usa el sistema; un humano puede corregirlos.
 *  - cls_cafdia: categoría según el diagnóstico real del técnico (evaluación automática).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clasificacion_solicitud', function (Blueprint $table) {
            $table->id('cls_id');
            $table->unsignedBigInteger('cls_sseid');
            $table->string('cls_estado', 13)->default('PENDIENTE');

            // Dispositivo declarado por el cliente (NULL = lo dejó para la IA)
            $table->unsignedSmallInteger('cls_tdicli')->nullable();

            // Respuesta de la IA (no se modifica)
            $table->unsignedSmallInteger('cls_tdiia')->nullable();
            $table->unsignedInteger('cls_fllia')->nullable();
            $table->unsignedSmallInteger('cls_cafia')->nullable();
            $table->unsignedSmallInteger('cls_urgia')->nullable();
            $table->string('cls_confia', 5)->nullable();
            $table->string('cls_motivo', 300)->nullable();

            // Valores vigentes
            $table->unsignedSmallInteger('cls_tdiid')->nullable();
            $table->unsignedSmallInteger('cls_cafid')->nullable();
            $table->unsignedSmallInteger('cls_urgid')->nullable();

            // Evaluación
            $table->unsignedSmallInteger('cls_cafdia')->nullable();
            $table->timestamp('cls_evalua')->nullable();
            $table->timestamp('cls_revisa')->nullable();

            // Trazabilidad técnica
            $table->unsignedBigInteger('cls_dprid')->nullable();
            $table->string('cls_prmver', 10)->nullable();
            $table->unsignedInteger('cls_latms')->nullable();
            $table->smallInteger('cls_intent')->default(0);
            $table->timestamp('cls_creado')->useCurrent();
            $table->timestamp('cls_actual')->nullable();

            $table->unique('cls_sseid', 'uq_cls_sseid');

            $table->foreign('cls_sseid', 'fk_cls_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('cls_dprid', 'fk_cls_dpr')->references('dpr_id')->on('diagnostico_preliminar')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->foreign('cls_fllia', 'fk_cls_fllia')->references('fll_id')->on('falla')
                  ->onUpdate('cascade')->onDelete('restrict');

            foreach (['cls_tdicli', 'cls_tdiia', 'cls_tdiid'] as $col) {
                $table->foreign($col, "fk_{$col}")->references('tdi_id')->on('tipo_dispositivo')
                      ->onUpdate('cascade')->onDelete('restrict');
            }
            foreach (['cls_cafia', 'cls_cafid', 'cls_cafdia'] as $col) {
                $table->foreign($col, "fk_{$col}")->references('caf_id')->on('categoria_falla')
                      ->onUpdate('cascade')->onDelete('restrict');
            }
            foreach (['cls_urgia', 'cls_urgid'] as $col) {
                $table->foreign($col, "fk_{$col}")->references('urg_id')->on('urgencia_servicio')
                      ->onUpdate('cascade')->onDelete('restrict');
            }

            $table->index('cls_estado', 'ix_cls_estado');
            $table->index('cls_cafid', 'ix_cls_cafid');
            $table->index('cls_urgid', 'ix_cls_urgid');
        });

        DB::statement("ALTER TABLE clasificacion_solicitud ADD CONSTRAINT ck_cls_estado
            CHECK (cls_estado IN ('PENDIENTE', 'IA', 'NO_DISPONIBLE', 'MANUAL'))");
        DB::statement("ALTER TABLE clasificacion_solicitud ADD CONSTRAINT ck_cls_confia
            CHECK (cls_confia IS NULL OR cls_confia IN ('ALTA', 'MEDIA', 'BAJA'))");

        // Auditoría de correcciones humanas
        Schema::create('clasificacion_correccion', function (Blueprint $table) {
            $table->id('ccr_id');
            $table->unsignedBigInteger('ccr_clsid');
            $table->unsignedInteger('ccr_usuid');
            $table->string('ccr_campo', 11);
            $table->string('ccr_valant', 60)->nullable();
            $table->string('ccr_valnue', 60);
            $table->string('ccr_motivo', 255)->nullable();
            $table->timestamp('ccr_fecha')->useCurrent();

            $table->foreign('ccr_clsid', 'fk_ccr_cls')->references('cls_id')->on('clasificacion_solicitud')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('ccr_usuid', 'fk_ccr_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->index('ccr_clsid', 'ix_ccr_clsid');
        });

        DB::statement("ALTER TABLE clasificacion_correccion ADD CONSTRAINT ck_ccr_campo
            CHECK (ccr_campo IN ('DISPOSITIVO', 'CATEGORIA', 'URGENCIA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificacion_correccion');
        Schema::dropIfExists('clasificacion_solicitud');
    }
};