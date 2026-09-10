<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_medida', function (Blueprint $table) {
            $table->increments('umd_id');
            $table->string('umd_codigo', 5)->unique('uq_umd_codigo');
            $table->string('umd_nombre', 40);
            // Indica si la unidad admite cantidades fraccionarias
            // (ej. Kilogramo = 1, Unidad = 0). Lo usará el futuro formulario
            // de producto para decidir el step del input de cantidad.
            $table->smallInteger('umd_decimal')->default(0);
            $table->smallInteger('umd_estado')->default(1);
        });

        DB::statement("ALTER TABLE unidad_medida
            ADD CONSTRAINT ck_umd_decimal CHECK (umd_decimal IN (0, 1))");
        DB::statement("ALTER TABLE unidad_medida
            ADD CONSTRAINT ck_umd_estado CHECK (umd_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_medida');
    }
};