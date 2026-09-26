<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Del timbrado del proveedor solo importa el VENCIMIENTO: la fecha de
 * emision de la factura se valida contra tpv_fechfn.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El CHECK depende de la columna: se elimina explicitamente primero.
        DB::statement('ALTER TABLE timbrado_proveedor DROP CONSTRAINT IF EXISTS ck_tpv_vigencia');

        Schema::table('timbrado_proveedor', function (Blueprint $table) {
            $table->dropColumn('tpv_fechin');
        });
    }

    public function down(): void
    {
        Schema::table('timbrado_proveedor', function (Blueprint $table) {
            $table->date('tpv_fechin')->nullable()->after('tpv_numero');
        });
    }
};