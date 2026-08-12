<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria', function (Blueprint $table) {
            $table->increments('cat_id');
            $table->unsignedInteger('cat_idpadr')->nullable();
            $table->integer('cat_nivel')->nullable();
            $table->string('cat_nombre', 45)->nullable();
        });

        // FK autorreferenciada: se agrega después de crear la tabla
        Schema::table('categoria', function (Blueprint $table) {
            $table->foreign('cat_idpadr', 'fk_categoria_categoria1')
                ->references('cat_id')->on('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropForeign('fk_categoria_categoria1');
        });

        Schema::dropIfExists('categoria');
    }
};
