<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_equivalencia', function (Blueprint $table) {
            $table->increments('peq_id');
            $table->unsignedInteger('peq_prdid');
            $table->unsignedInteger('peq_umdid');

            // Cuantas unidades de STOCK (producto.prd_umdid) equivalen a
            // 1 de esta unidad alternativa. Ej: Caja -> 12.0000
            $table->decimal('peq_factor', 10, 4);
            $table->smallInteger('peq_estado')->default(1);

            $table->foreign('peq_prdid', 'fk_peq_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('peq_umdid', 'fk_peq_umd')
                  ->references('umd_id')->on('unidad_medida')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Un producto no puede tener 2 equivalencias para la misma unidad
            $table->unique(['peq_prdid', 'peq_umdid'], 'uq_peq_prd_umd');
        });

        DB::statement("ALTER TABLE producto_equivalencia ADD CONSTRAINT ck_peq_factor CHECK (peq_factor > 0)");
        DB::statement("ALTER TABLE producto_equivalencia ADD CONSTRAINT ck_peq_estado CHECK (peq_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_equivalencia');
    }
};