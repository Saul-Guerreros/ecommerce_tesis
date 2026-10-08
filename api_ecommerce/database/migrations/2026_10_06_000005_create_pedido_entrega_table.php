<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Snapshot del Paso 1 (ptm_entrega), ya resuelto a filas reales.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_entrega', function (Blueprint $table) {
            $table->id('pen_id');
            $table->unsignedBigInteger('pen_pedid');
            $table->unsignedSmallInteger('pen_tenid');
            $table->unsignedInteger('pen_sucid')->nullable();
            $table->unsignedInteger('pen_dirid')->nullable();
            $table->string('pen_nrodoc', 20);
            $table->string('pen_nombre', 100);
            $table->string('pen_apelli', 100);
            $table->string('pen_telef', 45)->nullable();
            $table->string('pen_departa', 60)->nullable();
            $table->string('pen_ciudad', 60)->nullable();
            $table->string('pen_barrio', 60)->nullable();
            $table->string('pen_calle', 60)->nullable();
            $table->string('pen_nrocasa', 20)->nullable();
            $table->string('pen_referencia', 250)->nullable();

            $table->foreign('pen_pedid')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pen_tenid')->references('ten_id')->on('tipo_entrega')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('pen_sucid')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('pen_dirid')->references('dir_id')->on('direccion')
                  ->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_entrega');
    }
};