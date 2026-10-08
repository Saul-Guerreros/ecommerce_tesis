<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifa_envio', function (Blueprint $table) {
            $table->increments('tev_id');
            $table->unsignedInteger('tev_sucid');
            $table->unsignedInteger('tev_ciuid');
            $table->decimal('tev_costo', 10, 2);
            $table->timestamp('tev_creado')->useCurrent();

            $table->foreign('tev_sucid', 'fk_tev_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('tev_ciuid', 'fk_tev_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['tev_sucid', 'tev_ciuid'], 'uq_tev_suc_ciudad');
        });

        DB::statement('ALTER TABLE tarifa_envio ADD CONSTRAINT ck_tev_costo CHECK (tev_costo >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifa_envio');
    }
};