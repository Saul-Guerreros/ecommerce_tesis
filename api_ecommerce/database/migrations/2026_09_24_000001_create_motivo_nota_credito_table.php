<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivo_nota_credito', function (Blueprint $table) {
            $table->increments('mnc_id');
            $table->string('mnc_codigo', 10)->unique('uq_mnc_codigo');
            $table->string('mnc_descri', 60);
            // Unica fuente de verdad: si la NC descuenta CANTIDAD de stock.
            $table->smallInteger('mnc_afesto')->default(0);
            $table->smallInteger('mnc_estado')->default(1);
        });

        DB::statement("ALTER TABLE motivo_nota_credito ADD CONSTRAINT ck_mnc_afesto CHECK (mnc_afesto IN (0,1))");
        DB::statement("ALTER TABLE motivo_nota_credito ADD CONSTRAINT ck_mnc_estado CHECK (mnc_estado IN (0,1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('motivo_nota_credito');
    }
};