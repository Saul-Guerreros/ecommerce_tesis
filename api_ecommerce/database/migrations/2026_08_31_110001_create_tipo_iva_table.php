<?php
// database/migrations/2026_08_31_110001_create_tipo_iva_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_iva', function (Blueprint $table) {
            $table->increments('tiv_id');
            $table->string('tiv_descri', 45);
            $table->decimal('tiv_tasa', 5, 2);
            $table->smallInteger('tiv_estado')->default(1);
            $table->timestamp('tiv_creado')->useCurrent();

            $table->unique('tiv_descri', 'uq_tiv_descri');
            // Una sola fila por tasa: dos "IVA 10%" romperian el desglose.
            $table->unique('tiv_tasa', 'uq_tiv_tasa');
        });

        DB::statement("ALTER TABLE tipo_iva
            ADD CONSTRAINT ck_tiv_estado CHECK (tiv_estado IN (0, 1))");

        DB::statement("ALTER TABLE tipo_iva
            ADD CONSTRAINT ck_tiv_tasa CHECK (tiv_tasa >= 0 AND tiv_tasa <= 100)");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_iva');
    }
};
