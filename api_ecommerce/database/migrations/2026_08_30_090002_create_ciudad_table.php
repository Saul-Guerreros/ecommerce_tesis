<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudad', function (Blueprint $table) {
            $table->increments('ciu_id');
            $table->unsignedInteger('ciu_depid');
            $table->string('ciu_descri', 50);
            $table->smallInteger('ciu_estado')->default(1);
            $table->timestamp('ciu_creado')->useCurrent();

            $table->foreign('ciu_depid', 'fk_ciu_dep')
                  ->references('dep_id')->on('departamento')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['ciu_depid', 'ciu_descri'], 'uq_ciu_dep_descri');
            $table->index('ciu_depid', 'ix_ciu_depid');
        });

        DB::statement("ALTER TABLE ciudad
            ADD CONSTRAINT ck_ciu_estado CHECK (ciu_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudad');
    }
};