<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto', function (Blueprint $table) {
            $table->increments('prd_id');
            $table->unsignedInteger('prd_marid');
            $table->unsignedInteger('prd_catid');
            $table->string('prd_titulo', 45)->nullable();
            $table->string('prd_codinv', 45);
            $table->text('prd_descri');
            $table->decimal('prd_precio', 10, 2)->nullable();
            $table->integer('prd_stock')->nullable();
            $table->integer('prd_stkmin');
            $table->integer('prd_estado')->nullable();
            $table->timestamp('prd_creado')->nullable();

            $table->unique('prd_codinv', 'prd_codinv_UNIQUE');

            $table->foreign('prd_catid', 'fk_producto_categoria1')
                ->references('cat_id')->on('categoria');

            $table->foreign('prd_marid', 'fk_producto_marca1')
                ->references('mar_id')->on('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
