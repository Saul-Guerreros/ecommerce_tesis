<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrupador de profesion: es el primero de los dos desplegables
 * encadenados del Paso 1/3 (Tipo de profesion -> Profesion).
 *
 * No se guarda en solicitud_credito: se deduce de
 * profesion.pro_tprid. Guardar los dos seria duplicar un dato que
 * puede quedar inconsistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_profesion', function (Blueprint $table) {
            $table->smallIncrements('tpr_id');
            $table->string('tpr_descri', 60);
            $table->smallInteger('tpr_estado')->default(1);
            $table->timestamp('tpr_creado')->useCurrent();

            $table->unique('tpr_descri', 'uq_tipo_profesion_tpr_descri');
        });

        DB::statement("ALTER TABLE tipo_profesion
            ADD CONSTRAINT ck_tpr_estado CHECK (tpr_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_profesion');
    }
};