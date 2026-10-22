<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garantía de COMPRA gestionada con la empresa responsable (distribuidora/fabricante).
 *
 * La garantía de una compra la cubre la empresa que respalda el producto: el taller no
 * diagnostica ni repara, sino que recibe el equipo, lo gestiona con la empresa (envío,
 * seguimiento, retorno) y avisa al cliente cuando está listo.
 *
 * producto.prd_proid          Empresa (proveedor) responsable de la garantía del producto.
 * garantia_proveedor          Gestión de una solicitud de garantía de compra con esa empresa
 *                             (una por solicitud): caso, contacto, envío, retorno y resultado.
 * estado_servicio             Dos estados nuevos del ciclo técnico: GESTION_GARANTIA y EN_EMPRESA.
 *
 * El reclamo de garantía de una reparación hecha por el propio taller (sse_sseori)
 * NO cambia: sigue el ciclo normal de diagnóstico, presupuesto sin cargo y reparación.
 */
return new class extends Migration
{
    /** código => [orden, descripción, visible al cliente]. null = ya existe, solo se reordena. */
    private const ESTADOS = [
        'SOLICITADA'       => [1,  null, null],
        'RECIBIDA'         => [2,  null, null],
        'EN_DIAGNOSTICO'   => [3,  null, null],
        'PRESUPUESTADA'    => [4,  null, null],
        'APROBADA'         => [5,  null, null],
        'EN_REPARACION'    => [6,  null, null],
        'GESTION_GARANTIA' => [7,  'Gestión de garantía con la empresa',     'EN GESTIÓN'],
        'EN_EMPRESA'       => [8,  'En la empresa responsable de la garantía', 'EN LA EMPRESA'],
        'LISTA'            => [9,  null, null],
        'ENTREGADA'        => [10, null, null],
        'CANCELADA'        => [11, null, null],
    ];

    /** Orden anterior, para el rollback. */
    private const ORDEN_ANTERIOR = [
        'SOLICITADA' => 1, 'RECIBIDA' => 2, 'EN_DIAGNOSTICO' => 3, 'PRESUPUESTADA' => 4, 'APROBADA' => 5,
        'EN_REPARACION' => 6, 'LISTA' => 7, 'ENTREGADA' => 8, 'CANCELADA' => 9,
    ];

    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_proid')->nullable();
            $table->foreign('prd_proid', 'fk_prd_pro')->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->index('prd_proid', 'ix_prd_proid');
        });

        Schema::create('garantia_proveedor', function (Blueprint $table) {
            $table->id('gpr_id');
            $table->unsignedBigInteger('gpr_sseid');
            $table->unsignedInteger('gpr_proid')->nullable();
            $table->string('gpr_nrocaso', 50)->nullable();
            $table->string('gpr_contacto', 150)->nullable();
            $table->date('gpr_fecenv')->nullable();
            $table->date('gpr_fecrec')->nullable();
            $table->string('gpr_result', 12)->nullable();
            $table->string('gpr_nroserr', 80)->nullable();
            $table->string('gpr_obs', 500)->nullable();
            $table->unsignedInteger('gpr_usuid')->nullable();
            $table->timestamp('gpr_creado')->useCurrent();
            $table->timestamp('gpr_actual')->nullable();

            $table->unique('gpr_sseid', 'uq_gpr_sse');
            $table->foreign('gpr_sseid', 'fk_gpr_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('gpr_proid', 'fk_gpr_pro')->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('gpr_usuid', 'fk_gpr_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
            $table->index('gpr_proid', 'ix_gpr_proid');
        });

        DB::statement("ALTER TABLE garantia_proveedor ADD CONSTRAINT ck_gpr_result
            CHECK (gpr_result IS NULL OR gpr_result IN ('REPARADO', 'REEMPLAZADO', 'RECHAZADO'))");
        DB::statement('ALTER TABLE garantia_proveedor ADD CONSTRAINT ck_gpr_fechas
            CHECK (gpr_fecrec IS NULL OR gpr_fecenv IS NULL OR gpr_fecrec >= gpr_fecenv)');

        // Catálogo de estados: los dos nuevos entran en su lugar del ciclo y el resto se reordena.
        foreach (self::ESTADOS as $codigo => [$orden, $descri, $visible]) {
            if ($descri === null) {
                DB::table('estado_servicio')->where('ets_codigo', $codigo)->update(['ets_orden' => $orden]);
                continue;
            }

            DB::table('estado_servicio')->updateOrInsert(
                ['ets_codigo' => $codigo],
                ['ets_descri' => $descri, 'ets_visible' => $visible, 'ets_orden' => $orden, 'ets_estado' => 1],
            );
        }
    }

    public function down(): void
    {
        // Las solicitudes que ya usan los estados nuevos impiden este rollback (FK RESTRICT).
        DB::table('estado_servicio')->whereIn('ets_codigo', ['GESTION_GARANTIA', 'EN_EMPRESA'])->delete();
        foreach (self::ORDEN_ANTERIOR as $codigo => $orden) {
            DB::table('estado_servicio')->where('ets_codigo', $codigo)->update(['ets_orden' => $orden]);
        }

        DB::statement('ALTER TABLE garantia_proveedor DROP CONSTRAINT IF EXISTS ck_gpr_fechas');
        DB::statement('ALTER TABLE garantia_proveedor DROP CONSTRAINT IF EXISTS ck_gpr_result');
        Schema::dropIfExists('garantia_proveedor');

        Schema::table('producto', function (Blueprint $table) {
            $table->dropForeign('fk_prd_pro');
            $table->dropIndex('ix_prd_proid');
            $table->dropColumn('prd_proid');
        });
    }
};
