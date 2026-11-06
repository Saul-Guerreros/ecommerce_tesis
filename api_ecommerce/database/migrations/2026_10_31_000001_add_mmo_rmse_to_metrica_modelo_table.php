<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RMSE: criterio de selección del modelo (ver EvaluadorModelos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrica_modelo', function (Blueprint $table) {
            $table->decimal('mmo_rmse', 14, 4)->default(0)->after('mmo_modelo');
        });
    }

    public function down(): void
    {
        Schema::table('metrica_modelo', function (Blueprint $table) {
            $table->dropColumn('mmo_rmse');
        });
    }
};