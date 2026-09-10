<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposito', function (Blueprint $table) {
            $table->increments('dpo_id');
            $table->unsignedInteger('dpo_sucid');
            $table->string('dpo_codigo', 10);
            $table->string('dpo_nombre', 45);
            $table->string('dpo_descri', 45)->nullable();
            $table->smallInteger('dpo_princi')->default(0);
            $table->smallInteger('dpo_estado')->default(1);
            $table->timestamp('dpo_creado')->useCurrent();

            $table->foreign('dpo_sucid', 'fk_dpo_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['dpo_sucid', 'dpo_codigo'], 'uq_dpo_suc_codigo');
            $table->index('dpo_sucid', 'ix_dpo_sucid');
        });

        DB::statement("ALTER TABLE deposito
            ADD CONSTRAINT ck_dpo_estado CHECK (dpo_estado IN (0, 1))");

        DB::statement("ALTER TABLE deposito
            ADD CONSTRAINT ck_dpo_princi CHECK (dpo_princi IN (0, 1))");

        // Un solo depósito principal activo por sucursal.
        DB::statement("CREATE UNIQUE INDEX uq_dpo_princi_suc
            ON deposito (dpo_sucid)
            WHERE dpo_princi = 1 AND dpo_estado = 1");
    }

    public function down(): void
    {
        Schema::dropIfExists('deposito');
    }
};