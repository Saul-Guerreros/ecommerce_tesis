<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variacion_valor', function (Blueprint $table) {
            $table->unsignedInteger('vva_varid');
            $table->unsignedInteger('vva_prpid');
            $table->primary(['vva_varid', 'vva_prpid']);

            $table->foreign('vva_varid')->references('var_proid')->on('variacion_producto');
            $table->foreign('vva_prpid')->references('prp_id')->on('propiedad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variacion_valor');
    }
};
