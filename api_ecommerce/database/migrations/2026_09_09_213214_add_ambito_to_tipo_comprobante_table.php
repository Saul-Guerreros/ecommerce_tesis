<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipo_comprobante', function (Blueprint $table) {
            $table->char('tco_ambito', 1)
                ->default('V')
                ->after('tco_descri');
        });

        DB::table('tipo_comprobante')
            ->where('tco_codigo', 'AUF')
            ->update(['tco_ambito' => 'C']);

        Schema::table('tipo_comprobante', function (Blueprint $table) {
            $table->char('tco_ambito', 1)->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tipo_comprobante', function (Blueprint $table) {
            $table->dropColumn('tco_ambito');
        });
    }
};