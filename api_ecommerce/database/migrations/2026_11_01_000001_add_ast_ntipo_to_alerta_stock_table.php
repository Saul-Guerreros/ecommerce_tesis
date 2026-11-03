<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ast_ntipo: último tipo de alerta avisado por correo.
 * Evita reenviar el mismo aviso todos los días; solo se vuelve a avisar
 * si la alerta empeora (crítico -> agotado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerta_stock', function (Blueprint $table) {
            $table->string('ast_ntipo', 12)->nullable()->after('ast_obs');
        });
    }

    public function down(): void
    {
        Schema::table('alerta_stock', function (Blueprint $table) {
            $table->dropColumn('ast_ntipo');
        });
    }
};