<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 7 - Tipo de problema: agrupa las fallas en categorías.
 * La categoría de una solicitud se DERIVA de su falla principal (no la elige la IA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_falla', function (Blueprint $table) {
            $table->smallIncrements('caf_id');
            $table->string('caf_codigo', 20);
            $table->string('caf_descri', 60);
            $table->smallInteger('caf_estado')->default(1);
            $table->timestamp('caf_creado')->useCurrent();

            $table->unique('caf_codigo', 'uq_caf_codigo');
            $table->unique('caf_descri', 'uq_caf_descri');
        });

        DB::statement("ALTER TABLE categoria_falla ADD CONSTRAINT ck_caf_estado CHECK (caf_estado IN (0, 1))");

        Schema::table('falla', function (Blueprint $table) {
            $table->unsignedSmallInteger('fll_cafid')->nullable();

            $table->foreign('fll_cafid', 'fk_fll_caf')->references('caf_id')->on('categoria_falla')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->index('fll_cafid', 'ix_fll_cafid');
        });
    }

    public function down(): void
    {
        Schema::table('falla', function (Blueprint $table) {
            $table->dropForeign('fk_fll_caf');
            $table->dropIndex('ix_fll_cafid');
            $table->dropColumn('fll_cafid');
        });

        Schema::dropIfExists('categoria_falla');
    }
};