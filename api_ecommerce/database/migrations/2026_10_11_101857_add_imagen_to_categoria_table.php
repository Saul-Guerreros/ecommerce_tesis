<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            // Ruta relativa en el disco "public" (ej. categorias/abc.webp).
            // Solo la usan las categorías principales (nivel 1).
            $table->string('cat_imagen', 255)->nullable()->after('cat_icono');
        });
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropColumn('cat_imagen');
        });
    }
};