<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Estado de cada versión de presupuesto (presupuesto_servicio.pse_eprid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_presupuesto', function (Blueprint $table) {
            $table->smallIncrements('epr_id');
            $table->string('epr_codigo', 20);
            $table->string('epr_descri', 50);
            $table->smallInteger('epr_estado')->default(1);
            $table->timestamp('epr_creado')->useCurrent();

            $table->unique('epr_codigo', 'uq_epr_codigo');
        });

        DB::statement("ALTER TABLE estado_presupuesto ADD CONSTRAINT ck_epr_estado CHECK (epr_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_presupuesto');
    }
};
