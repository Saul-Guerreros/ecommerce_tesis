<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite marcar que un producto es "el mismo modelo" que otro, pero con
 * una variante independiente (color, edición, etc.) que se vende y se
 * gestiona como un producto 100% separado (propio SKU, precio, stock,
 * publicación web e imágenes). No genera combinaciones ni pivotes: es
 * solo un puntero para poder mostrar "otras opciones de este modelo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_padre_id')->nullable()->after('prd_id');
            $table->foreign('prd_padre_id')->references('prd_id')->on('producto')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropForeign(['prd_padre_id']);
            $table->dropColumn('prd_padre_id');
        });
    }
};