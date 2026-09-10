<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_sucursal', function (Blueprint $table) {
            $table->increments('uss_id');
            $table->unsignedInteger('uss_usuid');
            $table->unsignedInteger('uss_sucid');
            $table->smallInteger('uss_princi')->default(0);
            $table->smallInteger('uss_estado')->default(1);
            $table->timestamp('uss_creado')->useCurrent();

            $table->foreign('uss_usuid', 'fk_uss_usu')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('uss_sucid', 'fk_uss_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            // No se puede asignar la misma sucursal dos veces al mismo usuario.
            $table->unique(['uss_usuid', 'uss_sucid'], 'uq_uss_usu_suc');
        });

        DB::statement("ALTER TABLE usuario_sucursal ADD CONSTRAINT ck_uss_princi CHECK (uss_princi IN (0, 1))");
        DB::statement("ALTER TABLE usuario_sucursal ADD CONSTRAINT ck_uss_estado CHECK (uss_estado IN (0, 1))");

        // Un solo toggle de "principal" activo por usuario, mismo criterio
        // que ya usamos en dpo_princi (Deposito) y suc_princi (Sucursal).
        DB::statement("CREATE UNIQUE INDEX uq_uss_princi_usu
            ON usuario_sucursal (uss_usuid)
            WHERE uss_princi = 1 AND uss_estado = 1");
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_sucursal');
    }
};