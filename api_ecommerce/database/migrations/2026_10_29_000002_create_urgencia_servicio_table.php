<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 7 - Niveles de urgencia con su criterio escrito.
 * El criterio se inyecta en el prompt. La urgencia NO altera el orden de atención (FIFO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgencia_servicio', function (Blueprint $table) {
            $table->smallIncrements('urg_id');
            $table->string('urg_codigo', 10);
            $table->string('urg_descri', 30);
            $table->string('urg_criter', 400);
            $table->string('urg_color', 15)->default('secondary');   // color de badge CoreUI
            $table->smallInteger('urg_orden')->default(0);
            $table->smallInteger('urg_estado')->default(1);
            $table->timestamp('urg_creado')->useCurrent();

            $table->unique('urg_codigo', 'uq_urg_codigo');
        });

        DB::statement("ALTER TABLE urgencia_servicio ADD CONSTRAINT ck_urg_estado CHECK (urg_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('urgencia_servicio');
    }
};