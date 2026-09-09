<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_ajuste', function (Blueprint $table) {
            $table->increments('tpa_id');
            $table->string('tpa_codig', 10)->unique('uq_tpa_codig');
            $table->string('tpa_descri', 60);
            // 'P' Positivo (sobrante) / 'N' Negativo (merma, rotura, robo, vencimiento)
            $table->char('tpa_natura', 1);
            // Si exige que ajuste_inventario.aji_referencia sea obligatoria
            $table->smallInteger('tpa_reqcom')->default(0);
            $table->smallInteger('tpa_estado')->default(1);
        });

        DB::statement("ALTER TABLE tipo_ajuste ADD CONSTRAINT ck_tpa_natura CHECK (tpa_natura IN ('P', 'N'))");
        DB::statement("ALTER TABLE tipo_ajuste ADD CONSTRAINT ck_tpa_reqcom CHECK (tpa_reqcom IN (0, 1))");
        DB::statement("ALTER TABLE tipo_ajuste ADD CONSTRAINT ck_tpa_estado CHECK (tpa_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_ajuste');
    }
};