<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_pago', function (Blueprint $table) {
            $table->smallIncrements('epa_id');
            $table->string('epa_codigo', 20);
            $table->string('epa_descri', 50);
            $table->smallInteger('epa_estado')->default(1);
            $table->timestamp('epa_creado')->useCurrent();

            $table->unique('epa_codigo', 'uq_epa_codigo');
        });

        DB::statement("ALTER TABLE estado_pago ADD CONSTRAINT ck_epa_estado CHECK (epa_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_pago');
    }
};