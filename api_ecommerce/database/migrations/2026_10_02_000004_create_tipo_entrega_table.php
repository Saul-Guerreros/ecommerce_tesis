<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo referencial RETIRO / ENVIO. Nace en el Paso 1, pero su destino
 * final es pedido.ped_tenid: el Paso 3 copia el ten_id directo, sin
 * traducir strings con un match() hardcodeado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_entrega', function (Blueprint $table) {
            $table->smallIncrements('ten_id');
            $table->string('ten_codigo', 20);
            $table->string('ten_descri', 50);
            $table->smallInteger('ten_estado')->default(1);
            $table->timestamp('ten_creado')->useCurrent();

            $table->unique('ten_codigo', 'uq_ten_codigo');
        });

        DB::statement("ALTER TABLE tipo_entrega
            ADD CONSTRAINT ck_ten_estado CHECK (ten_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_entrega');
    }
};