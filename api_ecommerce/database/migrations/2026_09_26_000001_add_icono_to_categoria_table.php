<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            // Nombre de un ícono de UIkit (ej. "desktop", "tablet", "cog").
            // Opcional: si queda null, el frontend usa un ícono genérico.
            $table->string('cat_icono', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropColumn('cat_icono');
        });
    }
};