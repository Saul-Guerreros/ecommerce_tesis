<?php
// database/migrations/2026_09_26_000001_multiples_presentaciones_producto_equivalencia.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La identidad de una presentación pasa a ser producto + unidad + factor:
        // Caja x10 y Caja x12 conviven, pero no se repite la misma combinación.
        Schema::table('producto_equivalencia', function (Blueprint $table) {
            $table->dropUnique('uq_peq_prd_umd');
            $table->unique(['peq_prdid', 'peq_umdid', 'peq_factor'], 'uq_peq_prd_umd_fac');
        });

        // La línea de compra debe saber QUÉ presentación usó (cmd_umdid solo ya es ambiguo).
        // Nullable: las líneas en unidad base no usan equivalencia.
        Schema::table('compra_detalle', function (Blueprint $table) {
            $table->unsignedInteger('cmd_peqid')->nullable()->after('cmd_umdid');

            $table->foreign('cmd_peqid', 'fk_cmd_peq')
                  ->references('peq_id')->on('producto_equivalencia')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        // Backfill: hasta hoy había una sola equivalencia por (producto, unidad),
        // así que las compras existentes apuntan sin ambigüedad a esa fila.
        DB::statement("
            UPDATE compra_detalle cd
            SET cmd_peqid = pe.peq_id
            FROM producto_equivalencia pe
            WHERE pe.peq_prdid = cd.cmd_prdid
              AND pe.peq_umdid = cd.cmd_umdid
        ");
    }

    public function down(): void
    {
        Schema::table('compra_detalle', function (Blueprint $table) {
            $table->dropForeign('fk_cmd_peq');
            $table->dropColumn('cmd_peqid');
        });

        // Fallará si ya existen dos presentaciones de la misma unidad.
        Schema::table('producto_equivalencia', function (Blueprint $table) {
            $table->dropUnique('uq_peq_prd_umd_fac');
            $table->unique(['peq_prdid', 'peq_umdid'], 'uq_peq_prd_umd');
        });
    }
};