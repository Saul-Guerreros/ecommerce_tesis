<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTA: si ya creaste estas tablas directamente con tu script SQL (MySQL Workbench),
 * puedes saltarte esta migración o marcarla como ya ejecutada con:
 *   php artisan migrate --pretend
 * Se incluye aquí solo para mantener el esquema versionado dentro de Laravel.
 *
 * Cambios sugeridos respecto a tu DDL original:
 * - var_preadi -> decimal(10,2) en vez de decimal() sin escala (se trunca a enteros si no).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variacion_producto', function (Blueprint $table) {
            $table->increments('var_proid');
            $table->unsignedInteger('var_padrid')->nullable(); // sin uso en el flujo actual, reservado a futuro
            $table->unsignedInteger('var_prdid');
            $table->decimal('var_preadi', 10, 2);
            $table->integer('var_stock');
            $table->integer('var_stkmin');
            $table->tinyInteger('var_estado');
            $table->timestamp('var_creado')->useCurrent();

            $table->foreign('var_prdid')->references('prd_id')->on('producto');
            $table->foreign('var_padrid')->references('var_proid')->on('variacion_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variacion_producto');
    }
};
