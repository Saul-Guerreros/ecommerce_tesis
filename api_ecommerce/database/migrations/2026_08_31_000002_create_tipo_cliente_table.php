<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_cliente', function (Blueprint $table) {
            $table->id('tpc_id');
            $table->string('tpc_descri', 50)->unique('tipo_cliente_descri_unique');
            $table->smallInteger('tpc_estado')->default(1);
            $table->timestamp('tpc_creado')->useCurrent();
        });

        DB::statement("ALTER TABLE tipo_cliente ADD CONSTRAINT tipo_cliente_estado_check
                       CHECK (tpc_estado IN (0,1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_cliente');
    }
};