<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajuste_inventario', function (Blueprint $table) {
            $table->increments('aji_id');
            $table->unsignedInteger('aji_usuid')->nullable();
            $table->unsignedInteger('aji_tpaid');
            $table->unsignedInteger('aji_depid');
            $table->date('aji_fecha');
            $table->string('aji_referencia', 45)->nullable();
            $table->string('aji_obs', 45)->nullable();

            // 0 Borrador (cargado, stock NO afectado)
            // 1 Confirmado (stock y kardex ya impactados, inmutable)
            // 2 Anulado
            $table->smallInteger('aji_estado')->default(0);

            $table->timestamp('aji_creado')->useCurrent();
            $table->timestamp('aji_confir')->nullable();
            $table->timestamp('aji_anulado')->nullable();
            $table->unsignedInteger('aji_usconf')->nullable();
            $table->unsignedInteger('aji_usanul')->nullable();

            $table->foreign('aji_tpaid', 'fk_aji_tpa')
                  ->references('tpa_id')->on('tipo_ajuste')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('aji_depid', 'fk_aji_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE ajuste_inventario ADD CONSTRAINT ck_aji_estado CHECK (aji_estado IN (0, 1, 2))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_inventario');
    }
};