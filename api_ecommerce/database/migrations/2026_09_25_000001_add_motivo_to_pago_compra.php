<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo obligatorio al anular un pago: evidencia de por que se
 * revirtio, para trazabilidad de control interno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_compra', function (Blueprint $table) {
            $table->string('pgo_motanu', 150)->nullable()->after('pgo_anusid');
        });
    }

    public function down(): void
    {
        Schema::table('pago_compra', function (Blueprint $table) {
            $table->dropColumn('pgo_motanu');
        });
    }
};