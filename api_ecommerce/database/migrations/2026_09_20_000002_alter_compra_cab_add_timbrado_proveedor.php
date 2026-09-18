<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1) compra_cab referencia al timbrado del proveedor (com_tpvid).
 *    com_timbra / com_timbvn se mantienen como SNAPSHOT historico.
 * 2) Se reemplaza el UNIQUE (com_proid, com_timbra, com_nrodoc):
 *    - con com_timbra NULL, PostgreSQL permitia duplicados;
 *    - no consideraba el tipo de comprobante;
 *    - bloqueaba volver a cargar una factura cuya compra fue ANULADA.
 *    El nuevo indice es parcial: solo compras confirmadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_cab', function (Blueprint $table) {
            $table->unsignedInteger('com_tpvid')->nullable()->after('com_tcoid');

            $table->foreign('com_tpvid', 'fk_com_tpv')
                  ->references('tpv_id')->on('timbrado_proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('com_tpvid', 'ix_com_tpvid');
            $table->dropUnique('uq_com_doc_por_proveedor');
        });

        DB::statement("CREATE UNIQUE INDEX uq_com_doc_confirmado
            ON compra_cab (com_proid, com_tcoid, COALESCE(com_timbra, ''), com_nrodoc)
            WHERE com_estfac = 1");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_com_doc_confirmado');

        Schema::table('compra_cab', function (Blueprint $table) {
            $table->dropForeign('fk_com_tpv');
            $table->dropIndex('ix_com_tpvid');
            $table->dropColumn('com_tpvid');
            $table->unique(['com_proid', 'com_timbra', 'com_nrodoc'], 'uq_com_doc_por_proveedor');
        });
    }
};
