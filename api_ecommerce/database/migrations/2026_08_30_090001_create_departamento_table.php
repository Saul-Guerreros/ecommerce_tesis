<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento', function (Blueprint $table) {
            $table->increments('dep_id');
            $table->string('dep_descri', 50);
            $table->smallInteger('dep_estado')->default(1);
            $table->timestamp('dep_creado')->useCurrent();

            $table->unique('dep_descri', 'uq_dep_descri');
        });

        DB::statement("ALTER TABLE departamento
            ADD CONSTRAINT ck_dep_estado CHECK (dep_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento');
    }
};