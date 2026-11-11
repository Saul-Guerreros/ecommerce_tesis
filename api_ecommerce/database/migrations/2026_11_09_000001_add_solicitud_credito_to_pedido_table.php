<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula el pedido con la solicitud de crédito de la que nace (etapa D).
 * UNIQUE: una solicitud genera como máximo UN pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->unsignedBigInteger('ped_scrid')->nullable();

            $table->foreign('ped_scrid', 'fk_ped_solicitud_credito')
                ->references('scr_id')->on('solicitud_credito')
                ->onDelete('restrict');

            $table->unique('ped_scrid', 'uq_ped_scrid');
        });
    }

    public function down(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->dropForeign('fk_ped_solicitud_credito');
            $table->dropUnique('uq_ped_scrid');
            $table->dropColumn('ped_scrid');
        });
    }
};