<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagen_producto', function (Blueprint $table) {
            $table->increments('img_id');
            $table->unsignedInteger('img_prdid');
            $table->string('img_url', 255);
            $table->smallInteger('img_orden');
            $table->tinyInteger('img_princi');
            $table->timestamp('img_creado');

            $table->foreign('img_prdid', 'fk_imagen_producto_producto1')
                ->references('prd_id')->on('producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagen_producto');
    }
};
