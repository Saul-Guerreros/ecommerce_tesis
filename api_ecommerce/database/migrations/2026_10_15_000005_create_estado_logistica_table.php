<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Ejes 2 y 4: logística de INGRESO (sse_elging) y EGRESO (sse_elgegr)
 * del equipo. Una sola tabla separada por elg_ambito.
 *
 * La FK no puede garantizar que sse_elging apunte a un estado de ámbito
 * INGRESO: eso lo valida el Service al cambiar de estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_logistica', function (Blueprint $table) {
            $table->smallIncrements('elg_id');
            $table->string('elg_codigo', 20);
            $table->string('elg_descri', 50);
            $table->string('elg_ambito', 7);
            $table->smallInteger('elg_estado')->default(1);
            $table->timestamp('elg_creado')->useCurrent();

            $table->unique('elg_codigo', 'uq_elg_codigo');
            $table->index('elg_ambito', 'ix_elg_ambito');
        });

        DB::statement("ALTER TABLE estado_logistica ADD CONSTRAINT ck_elg_ambito CHECK (elg_ambito IN ('INGRESO', 'EGRESO'))");
        DB::statement("ALTER TABLE estado_logistica ADD CONSTRAINT ck_elg_estado CHECK (elg_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_logistica');
    }
};
