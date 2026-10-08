<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita el retiro en sucursal del Checkout - Paso 1. suc_retiro es
 * independiente de suc_estado: una sucursal operativa no necesariamente
 * atiende retiros del ecommerce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->boolean('suc_retiro')->default(false)->after('suc_estado');
            $table->string('suc_horario', 150)->nullable()->after('suc_retiro');
        });
    }

    public function down(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->dropColumn(['suc_retiro', 'suc_horario']);
        });
    }
};