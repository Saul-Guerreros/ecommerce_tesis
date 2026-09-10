<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calle', function (Blueprint $table) {
            $table->increments('cal_id');
            $table->unsignedInteger('cal_barid');
            $table->string('cal_descri', 80);
            $table->smallInteger('cal_estado')->default(1);
            $table->timestamp('cal_creado')->useCurrent();

            // Sin cal_ciuid: la ciudad se resuelve por
            // calle -> barrio -> ciudad. Guardarla acá también
            // sería una dependencia transitiva redundante, con
            // riesgo de quedar desincronizada si el barrio cambia
            // de ciudad (no ocurre en este dominio, pero igual
            // es una responsabilidad que no le corresponde a calle).
            $table->foreign('cal_barid', 'fk_cal_bar')
                  ->references('bar_id')->on('barrio')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Misma calle puede repetirse en barrios distintos; no en el mismo.
            $table->unique(['cal_barid', 'cal_descri'], 'uq_cal_bar_descri');
            $table->index('cal_barid', 'ix_cal_barid');
        });

        DB::statement("ALTER TABLE calle
            ADD CONSTRAINT ck_cal_estado CHECK (cal_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('calle');
    }
};
