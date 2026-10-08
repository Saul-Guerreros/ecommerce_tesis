<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_pedido', function (Blueprint $table) {
            $table->smallIncrements('est_id');
            $table->string('est_codigo', 20);
            $table->string('est_descri', 50);
            $table->smallInteger('est_estado')->default(1);
            $table->timestamp('est_creado')->useCurrent();

            $table->unique('est_codigo', 'uq_est_codigo');
        });

        DB::statement("ALTER TABLE estado_pedido ADD CONSTRAINT ck_est_estado CHECK (est_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_pedido');
    }
};